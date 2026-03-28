<?php
namespace App\Controller;

use App\Entity\Event;
use App\Entity\EventImage;
use App\Repository\EventRepository;
use App\Repository\EventImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/events')]
class EventController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private EventRepository $eventRepo,
        private EventImageRepository $imageRepo,
        private ParameterBagInterface $params
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, $request->query->getInt('limit', 9));
        $all = $request->query->getBoolean('all', false);
        $search = trim($request->query->getString('search', ''));
        $status = trim($request->query->getString('status', ''));

        // Security: Only admins can view past events via the 'all' flag
        if ($all && !$this->isGranted('ROLE_ADMIN')) {
            $all = false; 
        }

        $result = $this->eventRepo->findPaginated(!$all, $page, $limit, $search, $status);
        $serialized = array_map(fn(Event $e) => $this->serialize($e), $result['data']);
        $lastPage = ceil($result['total'] / $limit);

        return $this->json([
            'data' => $serialized,
            'meta' => [
                'total' => $result['total'],
                'page' => $page,
                'last_page' => max(1, $lastPage),
                'limit' => $limit
            ]
        ]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $event = $this->eventRepo->find($id);
        if (!$event) return $this->json(['error' => 'Event not found'], Response::HTTP_NOT_FOUND);
        return $this->json($this->serialize($event));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $data = json_decode($request->getContent(), true);

        $event = new Event();
        $event->setTitle($data['title'] ?? '');
        $event->setDescription($data['description'] ?? '');
        $event->setDate(new \DateTimeImmutable($data['date'] ?? 'now'));
        $event->setEndDate(new \DateTimeImmutable($data['end_date'] ?? $data['date'] ?? 'now'));
        $event->setLocation($data['location'] ?? '');
        $event->setSeats((int)($data['seats'] ?? 0));

        $this->em->persist($event);
        $this->em->flush();

        return $this->json($this->serialize($event), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $event = $this->eventRepo->find($id);
        if (!$event) return $this->json(['error' => 'Event not found'], Response::HTTP_NOT_FOUND);

        $data = json_decode($request->getContent(), true);
        if (isset($data['title'])) $event->setTitle($data['title']);
        if (isset($data['description'])) $event->setDescription($data['description']);
        if (isset($data['date'])) $event->setDate(new \DateTimeImmutable($data['date']));
        if (isset($data['end_date'])) $event->setEndDate(new \DateTimeImmutable($data['end_date']));
        if (isset($data['location'])) $event->setLocation($data['location']);
        if (isset($data['seats'])) $event->setSeats((int)$data['seats']);

        $this->em->flush();
        return $this->json($this->serialize($event));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $event = $this->eventRepo->find($id);
        if (!$event) return $this->json(['error' => 'Event not found'], Response::HTTP_NOT_FOUND);

        // Files are deleted automatically if we set up an event listener,
        // but for simplicity we can just delete from disk here.
        $uploadDir = $this->params->get('uploads_dir');
        foreach ($event->getImages() as $img) {
            $filePath = $uploadDir . '/' . $img->getFilename();
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        $this->em->remove($event);
        $this->em->flush();
        return $this->json(['success' => true]);
    }

    #[Route('/{id}/images', methods: ['POST'])]
    public function uploadImages(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $event = $this->eventRepo->find($id);
        if (!$event) return $this->json(['error' => 'Event not found'], Response::HTTP_NOT_FOUND);

        /** @var UploadedFile[] $files */
        $files = $request->files->get('images');
        if (!$files) return $this->json(['error' => 'No images provided'], Response::HTTP_BAD_REQUEST);
        if (!is_array($files)) {
            $files = [$files];
        }

        $uploadDir = $this->params->get('uploads_dir');
        $position = $event->getImages()->count();
        $uploaded = [];

        foreach ($files as $file) {
            $ext = $file->guessExtension();
            if (!in_array($ext, ['jpeg', 'jpg', 'png', 'webp'])) {
                continue; // invalid format
            }

            $newFilename = uniqid() . '.' . $ext;

            try {
                $file->move($uploadDir, $newFilename);
            } catch (FileException $e) {
                return $this->json(['error' => 'File upload failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $image = new EventImage();
            $image->setFilename($newFilename);
            $image->setPosition($position++);
            $event->addImage($image);

            $this->em->persist($image);
            $uploaded[] = [
                'id' => $image->getId(),
                'url' => '/uploads/events/' . $newFilename
            ];
        }

        $this->em->flush();
        return $this->json(['success' => true, 'images' => $uploaded]);
    }

    #[Route('/{id}/images/{imageId}', methods: ['DELETE'])]
    public function deleteImage(int $id, int $imageId): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $event = $this->eventRepo->find($id);
        if (!$event) return $this->json(['error' => 'Event not found'], Response::HTTP_NOT_FOUND);

        $image = $this->imageRepo->find($imageId);
        if (!$image || $image->getEvent() !== $event) {
            return $this->json(['error' => 'Image not found'], Response::HTTP_NOT_FOUND);
        }

        $uploadDir = $this->params->get('uploads_dir');
        $filePath = $uploadDir . '/' . $image->getFilename();
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        $this->em->remove($image);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    private function serialize(Event $e): array
    {
        $images = [];
        foreach ($e->getImages() as $img) {
            $images[] = [
                'id' => $img->getId(),
                'url' => '/uploads/events/' . $img->getFilename(),
                'position' => $img->getPosition()
            ];
        }

        return [
            'id' => $e->getId(),
            'title' => $e->getTitle(),
            'description' => $e->getDescription(),
            'date' => $e->getDate()->format('Y-m-d H:i:s'),
            'end_date' => $e->getEndDate()->format('Y-m-d H:i:s'),
            'location' => $e->getLocation(),
            'seats' => $e->getSeats(),
            'available_seats' => $e->getAvailableSeats(),
            'status' => $e->getStatus(),
            'images' => $images,
        ];
    }
}
