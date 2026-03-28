<?php
namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;
use Resend;

#[Route('/api/reservations')]
class ReservationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventRepository $eventRepo,
        private ReservationRepository $reservationRepo,
        private SettingRepository $settingRepo,
        private Environment $twig
    ) {}

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $eventId = $data['event_id'] ?? null;

        if (!$eventId) return $this->json(['error' => 'event_id required'], Response::HTTP_BAD_REQUEST);

        $event = $this->eventRepo->find($eventId);
        if (!$event) return $this->json(['error' => 'Event not found'], Response::HTTP_NOT_FOUND);

        if ($event->getAvailableSeats() <= 0) {
            return $this->json(['error' => 'No seats available'], Response::HTTP_CONFLICT);
        }

        $email = $data['email'] ?? '';
        $name = $data['name'] ?? '';

        $reservation = new Reservation();
        $reservation->setEvent($event);
        $reservation->setName($name);
        $reservation->setEmail($email);
        $reservation->setPhone($data['phone'] ?? '');

        if ($this->getUser()) {
            $reservation->setUser($this->getUser());
        }

        // Check for Resend API key and send email
        $resendApiKey = $this->settingRepo->getValue('RESEND_API_KEY');
        if ($resendApiKey && $email) {
            try {
                $htmlBody = $this->twig->render('email/reservation_confirmation.html.twig', [
                    'name' => $name,
                    'email' => $email,
                    'event_title' => $event->getTitle(),
                    'event_date' => $event->getDate()->format('M j, Y - g:i A'),
                    'event_location' => $event->getLocation(),
                ]);

                $resend = Resend::client($resendApiKey);
                $resend->emails->send([
                    'from' => 'onboarding@resend.dev',
                    'to' => $email,
                    'subject' => 'Reservation Confirmed: ' . $event->getTitle(),
                    'html' => $htmlBody
                ]);
            } catch (\Exception $e) {
                return $this->json([
                    'error' => 'Failed to send confirmation email. Please contact administrators.'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // Only persist if email succeeds (or if email isn't configured)
        $this->em->persist($reservation);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Reservation confirmed!',
            'reservation' => [
                'id' => $reservation->getId(),
                'event' => $event->getTitle(),
                'name' => $reservation->getName(),
                'email' => $reservation->getEmail(),
                'created_at' => $reservation->getCreatedAt()->format('Y-m-d H:i:s')
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/event/{id}', methods: ['GET'])]
    public function byEvent(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $event = $this->eventRepo->find($id);
        if (!$event) return $this->json(['error' => 'Event not found'], Response::HTTP_NOT_FOUND);

        $reservations = $this->reservationRepo->findBy(['event' => $event]);
        return $this->json(array_map(fn(Reservation $r) => [
            'id' => $r->getId(),
            'name' => $r->getName(),
            'email' => $r->getEmail(),
            'phone' => $r->getPhone(),
            'created_at' => $r->getCreatedAt()->format('Y-m-d H:i:s')
        ], $reservations));
    }
}
