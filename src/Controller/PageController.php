<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PageController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('page/index.html.twig');
    }

    #[Route('/login', name: 'app_login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('page/login.html.twig');
    }

    #[Route('/event/{id}', name: 'app_event', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function event(int $id): Response
    {
        return $this->render('page/event.html.twig', [
            'id' => $id,
        ]);
    }

    #[Route('/admin', name: 'app_admin', methods: ['GET'])]
    public function admin(): Response
    {
        return $this->render('page/admin.html.twig');
    }

    #[Route('/passkey-test', name: 'app_passkey_test', methods: ['GET'])]
    public function passkeyTest(): Response
    {
        return $this->render('page/passkey_test.html.twig');
    }
}
