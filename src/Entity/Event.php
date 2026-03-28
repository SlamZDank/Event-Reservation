<?php
namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $endDate;

    #[ORM\Column(length: 255)]
    private string $location;

    #[ORM\Column]
    private int $seats;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: EventImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $images;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Reservation::class)]
    private Collection $reservations;

    public function __construct() { 
        $this->reservations = new ArrayCollection(); 
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $desc): self { $this->description = $desc; return $this; }
    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function setDate(\DateTimeImmutable $date): self { $this->date = $date; return $this; }
    public function getEndDate(): \DateTimeImmutable { return $this->endDate; }
    public function setEndDate(\DateTimeImmutable $endDate): self { $this->endDate = $endDate; return $this; }

    public function getStatus(): string
    {
        $now = new \DateTimeImmutable();
        if ($now > $this->endDate) {
            return 'passed';
        }
        if ($now >= $this->date && $now <= $this->endDate) {
            return 'ongoing';
        }
        return 'upcoming';
    }
    public function getLocation(): string { return $this->location; }
    public function setLocation(string $loc): self { $this->location = $loc; return $this; }
    public function getSeats(): int { return $this->seats; }
    public function setSeats(int $seats): self { $this->seats = $seats; return $this; }
    /**
     * @return Collection<int, EventImage>
     */
    public function getImages(): Collection { return $this->images; }
    
    public function addImage(EventImage $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setEvent($this);
        }
        return $this;
    }

    public function removeImage(EventImage $image): self
    {
        if ($this->images->removeElement($image)) {
            if ($image->getEvent() === $this) {
                $image->setEvent(null);
            }
        }
        return $this;
    }
    
    public function getReservations(): Collection { return $this->reservations; }
    public function getAvailableSeats(): int { return $this->seats - $this->reservations->count(); }
}
