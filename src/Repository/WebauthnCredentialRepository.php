<?php
namespace App\Repository;

use App\Entity\User;
use App\Entity\WebauthnCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Webauthn\Bundle\Repository\PublicKeyCredentialSourceRepositoryInterface;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

use Webauthn\Denormalizer\WebauthnSerializerFactory;

class WebauthnCredentialRepository extends ServiceEntityRepository implements PublicKeyCredentialSourceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry, private WebauthnSerializerFactory $serializerFactory)
    {
        parent::__construct($registry, WebauthnCredential::class);
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $entity = $this->findByCredentialId($publicKeyCredentialId);
        if (!$entity) {
            return null;
        }
        $serializer = $this->serializerFactory->create();
        return $serializer->deserialize($entity->getCredentialData(), PublicKeyCredentialSource::class, 'json');
    }

    public function findByCredentialId(string $publicKeyCredentialId): ?WebauthnCredential
    {
        $credentials = $this->createQueryBuilder('c')
            ->getQuery()
            ->getResult();

        foreach ($credentials as $c) {
            $serializer = $this->serializerFactory->create();
            $source = $serializer->deserialize($c->getCredentialData(), PublicKeyCredentialSource::class, 'json');
            $storedId = $source->publicKeyCredentialId;
            
            if ($storedId === $publicKeyCredentialId || bin2hex($storedId) === bin2hex($publicKeyCredentialId)) {
                return $c;
            }
        }
        return null;
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $userEntity): array
    {
        $credentials = $this->createQueryBuilder('c')
            ->join('c.user', 'u')
            ->where('u.email = :email')
            ->setParameter('email', $userEntity->name)
            ->getQuery()
            ->getResult();

        $serializer = $this->serializerFactory->create();

        return array_map(fn(WebauthnCredential $c) =>
            $serializer->deserialize($c->getCredentialData(), PublicKeyCredentialSource::class, 'json'),
            $credentials
        );
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $credentials = $this->findAll();
        $serializer = $this->serializerFactory->create();
        foreach ($credentials as $c) {
            $source = $serializer->deserialize($c->getCredentialData(), PublicKeyCredentialSource::class, 'json');
            if ($source->publicKeyCredentialId === $publicKeyCredentialSource->publicKeyCredentialId) {
                $c->setCredentialData($serializer->serialize($publicKeyCredentialSource, 'json'));
                $this->getEntityManager()->flush();
                return;
            }
        }
    }

    public function saveCredentialForUser(User $user, PublicKeyCredentialSource $source, string $name = 'My Passkey'): WebauthnCredential
    {
        $serializer = $this->serializerFactory->create();
        
        $credential = new WebauthnCredential();
        $credential->setUser($user);
        $credential->setCredentialData($serializer->serialize($source, 'json'));
        $credential->setName($name);

        $this->getEntityManager()->persist($credential);
        $this->getEntityManager()->flush();

        return $credential;
    }
}
