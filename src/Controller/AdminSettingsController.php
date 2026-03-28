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
        
        // Mask the API key for security if it exists, but show enough to know it's there
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
            // Only update if it's not empty, or if an explicit clear is requested (maybe later)
            // But if it's exactly the masked string, ignore it.
            if ($key !== '' && !str_contains($key, '...')) {
                $this->settingRepo->setValue('RESEND_API_KEY', $key);
            } elseif ($key === '') {
                // Clear it
                $this->settingRepo->setValue('RESEND_API_KEY', null);
            }
        }

        return $this->json(['success' => true]);
    }
}
