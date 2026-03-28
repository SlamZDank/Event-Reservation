<?php
namespace App\Controller;

use App\Repository\SettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/admin/settings')]
class AdminSettingsController extends AbstractController
{
    public function __construct(
        private SettingRepository $settingRepo
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $resendApiKey = $this->settingRepo->getValue('RESEND_API_KEY');
        
        // mask key but keep prefix visible
        $maskedResendKey = '';
        if ($resendApiKey) {
            if (strlen($resendApiKey) > 12) {
                $maskedResendKey = substr($resendApiKey, 0, 8) . '...' . substr($resendApiKey, -4);
            } else {
                $maskedResendKey = '********';
            }
        }

        return $this->json([
            'resend_api_key_masked' => $maskedResendKey,
            'resend_configured' => !empty($resendApiKey)
        ]);
    }

    #[Route('', methods: ['PUT'])]
    public function update(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);

        if (isset($data['resend_api_key'])) {
            $key = trim($data['resend_api_key']);
            // skip exact masked string, update otherwise
            if ($key !== '' && !str_contains($key, '...')) {
                $this->settingRepo->setValue('RESEND_API_KEY', $key);
            } elseif ($key === '') {
                // drop key
                $this->settingRepo->setValue('RESEND_API_KEY', null);
            }
        }

        return $this->json(['success' => true]);
    }
}
