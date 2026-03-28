<?php
namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\Bundle\Service\PublicKeyCredentialCreationOptionsFactory;
use Webauthn\Bundle\Service\PublicKeyCredentialRequestOptionsFactory;

#[Route('/api/passkey')]
class PasskeyController extends AbstractController
{
    private SerializerInterface $serializer;
    
    public function __construct(
        private PublicKeyCredentialCreationOptionsFactory $creationOptionsFactory,
        private PublicKeyCredentialRequestOptionsFactory $requestOptionsFactory,
        private AuthenticatorAttestationResponseValidator $attestationValidator,
        private AuthenticatorAssertionResponseValidator $assertionValidator,
        private WebauthnCredentialRepository $credentialRepo,
        private UserRepository $userRepo,
        private EntityManagerInterface $em,
        private JWTTokenManagerInterface $jwtManager,
        private CacheItemPoolInterface $cache,
        private \Webauthn\Denormalizer\WebauthnSerializerFactory $serializerFactory,
        #[Autowire('%env(RELYING_PARTY_ID)%')] private string $rpId,
    ) {
        $this->serializer = $this->serializerFactory->create();
    }



    #[Route('/register/options', methods: ['POST'])]
    public function registerOptions(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->json(['error' => 'Email required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepo->findByEmail($email);
        if ($user) {
            $currentUser = $this->getUser();
            if (!$currentUser || $currentUser->getUserIdentifier() !== $user->getEmail()) {
                return $this->json(['error' => 'You must be logged in to add a passkey to an existing account.'], Response::HTTP_FORBIDDEN);
            }
        } else {
            $user = new User();
            $user->setEmail($email);
            $this->em->persist($user);
            $this->em->flush();
        }

        $userEntity = new PublicKeyCredentialUserEntity(
            $user->getEmail(),
            $user->getId()->toBinary(),
            $user->getEmail()
        );

        $options = $this->creationOptionsFactory->create('default', $userEntity, []);

        // cache options by token
        $challengeToken = bin2hex(random_bytes(32));
        $cacheItem = $this->cache->getItem('webauthn_reg_' . $challengeToken);
        $cacheItem->set(serialize($options));
        $cacheItem->expiresAfter(300); // expires in 5m
        $this->cache->save($cacheItem);

        $rpEntity = $options->rp;
        
        $authSel = null;
        if ($options->authenticatorSelection) {
            $authSel = [];
            if ($options->authenticatorSelection->authenticatorAttachment) {
                $authSel['authenticatorAttachment'] = $options->authenticatorSelection->authenticatorAttachment;
            }
            if ($options->authenticatorSelection->residentKey) {
                $authSel['residentKey'] = $options->authenticatorSelection->residentKey;
            }
            if ($options->authenticatorSelection->userVerification) {
                $authSel['userVerification'] = $options->authenticatorSelection->userVerification;
            }
        }
        
        $json = json_encode([
            'challengeToken' => $challengeToken,
            'rp' => ['id' => $this->rpId, 'name' => $rpEntity->name],
            'user' => ['id' => base64_encode($options->user->id), 'name' => $options->user->name, 'displayName' => $options->user->displayName],
            'challenge' => base64_encode($options->challenge),
            'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
            'timeout' => $options->timeout,
            'attestation' => $options->attestation,
            'authenticatorSelection' => $authSel,
            'excludeCredentials' => [],
        ]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('/register/verify', methods: ['POST'])]
    public function registerVerify(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $challengeToken = $body['challengeToken'] ?? null;

        if (!$challengeToken) {
            return $this->json(['error' => 'Missing challengeToken'], Response::HTTP_BAD_REQUEST);
        }

        // get options
        $cacheKey = 'webauthn_reg_' . $challengeToken;
        $cacheItem = $this->cache->getItem($cacheKey);

        if (!$cacheItem->isHit()) {
            return $this->json(['error' => 'No registration in progress or challenge expired'], Response::HTTP_BAD_REQUEST);
        }

        $options = unserialize($cacheItem->get());

        try {
            // drop token before deser
            unset($body['challengeToken']);
            $credentialJson = json_encode($body);
            
            $publicKeyCredential = $this->serializer->deserialize(
                $credentialJson,
                \Webauthn\PublicKeyCredential::class,
                'json'
            );
            
            $authenticatorResponse = $publicKeyCredential->response;

            if (!$authenticatorResponse instanceof AuthenticatorAttestationResponse) {
                return $this->json(['error' => 'Invalid response type'], Response::HTTP_BAD_REQUEST);
            }

            $credentialSource = $this->attestationValidator->check(
                $authenticatorResponse,
                $options,
                $this->rpId,
            );

            $userHandle = $options->user->id;
            $users = $this->userRepo->findAll();
            $user = null;
            foreach ($users as $u) {
                if ($u->getId()->toBinary() === $userHandle) {
                    $user = $u;
                    break;
                }
            }

            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
            }

            $this->credentialRepo->saveCredentialForUser($user, $credentialSource);
            
            // kill used challenge
            $this->cache->deleteItem($cacheKey);

            $token = $this->jwtManager->create($user);

            return $this->json([
                'success' => true,
                'token' => $token,
                'user' => ['id' => $user->getId(), 'email' => $user->getEmail(), 'roles' => $user->getRoles()]
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'debug_class' => get_class($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/login/options', methods: ['POST'])]
    public function loginOptions(): JsonResponse
    {
        $options = $this->requestOptionsFactory->create('default', []);

        // cache options by token
        $challengeToken = bin2hex(random_bytes(32));
        $cacheItem = $this->cache->getItem('webauthn_login_' . $challengeToken);
        $cacheItem->set(serialize($options));
        $cacheItem->expiresAfter(300); // expires in 5m
        $this->cache->save($cacheItem);

        $json = json_encode([
            'challengeToken' => $challengeToken,
            'challenge' => base64_encode($options->challenge),
            'rpId' => $this->rpId,
            'timeout' => $options->timeout,
            'userVerification' => $options->userVerification,
            'allowCredentials' => [],
        ]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('/login/verify', methods: ['POST'])]
    public function loginVerify(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        $challengeToken = $body['challengeToken'] ?? null;

        if (!$challengeToken) {
            return $this->json(['error' => 'Missing challengeToken'], Response::HTTP_BAD_REQUEST);
        }

        // get options
        $cacheKey = 'webauthn_login_' . $challengeToken;
        $cacheItem = $this->cache->getItem($cacheKey);

        if (!$cacheItem->isHit()) {
            return $this->json(['error' => 'No login in progress or challenge expired'], Response::HTTP_BAD_REQUEST);
        }

        $options = unserialize($cacheItem->get());

        try {
            // drop token before deser
            unset($body['challengeToken']);
            $credentialJson = json_encode($body);
            
            $publicKeyCredential = $this->serializer->deserialize(
                $credentialJson,
                \Webauthn\PublicKeyCredential::class,
                'json'
            );
            
            $authenticatorResponse = $publicKeyCredential->response;

            if (!$authenticatorResponse instanceof AuthenticatorAssertionResponse) {
                return $this->json(['error' => 'Invalid response type'], Response::HTTP_BAD_REQUEST);
            }

            $entity = $this->credentialRepo->findByCredentialId($publicKeyCredential->rawId);

            if (!$entity) {
                return $this->json(['error' => 'Credential not found'], Response::HTTP_NOT_FOUND);
            }

            $credentialSource = $this->serializer->deserialize($entity->getCredentialData(), PublicKeyCredentialSource::class, 'json');

            $this->assertionValidator->check(
                $credentialSource,
                $authenticatorResponse,
                $options,
                $this->rpId,
                $credentialSource->userHandle,
            );

            $entity->setLastUsedAt(new \DateTimeImmutable());
            $this->em->flush();

            // kill used challenge
            $this->cache->deleteItem($cacheKey);

            $user = $entity->getUser();
            $token = $this->jwtManager->create($user);

            return $this->json([
                'success' => true,
                'token' => $token,
                'user' => ['id' => $user->getId(), 'email' => $user->getEmail(), 'roles' => $user->getRoles()]
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'debug_class' => get_class($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
