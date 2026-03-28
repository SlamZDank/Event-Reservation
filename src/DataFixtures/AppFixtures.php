<?php
namespace App\DataFixtures;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $manager): void
    {
        // 1. Create Core Users
        $admin = new User();
        $admin->setEmail('admin@event.com');
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        $mainUser = new User();
        $mainUser->setEmail('user@event.com');
        $mainUser->setPassword($this->hasher->hashPassword($mainUser, 'user123'));
        $manager->persist($mainUser);

        // 2. Generate Dummy Users
        $users = [$mainUser];
        for ($i = 1; $i <= 60; $i++) {
            $u = new User();
            $u->setEmail("student{$i}@issat.com");
            $u->setPassword($this->hasher->hashPassword($u, 'password'));
            $manager->persist($u);
            $users[] = $u;
        }

        // 3. Generate 60 Events
        $events = [];
        $topics = ['AI Summit', 'React Workshop', 'Symfony Days', 'Docker Masterclass', 'Cybersecurity Basics', 'Startup Pitch', 'Blockchain 101', 'Cloud Native Meetup', 'Python Bootcamp', 'UX Design Session'];
        $locations = ['ISSAT Amphitheater', 'Sousse Tech Hub', 'Online (Zoom)', 'Sousse Novotel', 'Campus Library'];

        for ($i = 1; $i <= 60; $i++) {
            $event = new Event();
            
            $topic = $topics[array_rand($topics)];
            $event->setTitle($topic . ' ' . $i);
            $event->setDescription("Join us for an exciting day exploring topics around {$topic}. Bring your laptop and questions!");
            
            // Random dates between -30 days and +180 days
            $daysOffset = rand(-30, 180);
            $date = new \DateTimeImmutable();
            $date = $date->modify(($daysOffset >= 0 ? '+' : '') . $daysOffset . ' days');
            $date = $date->setTime(rand(9, 17), 0);
            
            $event->setDate($date);
            
            // End date is 1-8 hours after start
            $durationHours = rand(1, 8);
            $event->setEndDate($date->modify("+{$durationHours} hours"));
            
            $event->setLocation($locations[array_rand($locations)]);
            
            // Random seats between 5 and 150
            $event->setSeats(rand(5, 150));
            
            $manager->persist($event);
            $events[] = $event;
        }

        // 4. Generate Exactly 60 Random Reservations
        // We have 60 events, we can just assign 1 reservation to each event
        for ($i = 0; $i < 60; $i++) {
            $event = $events[$i];
            
            // Randomly select a user for the reservation
            $randomUser = $users[array_rand($users)];
            
            $res = new Reservation();
            $res->setEvent($event);
            $res->setUser($randomUser);
            
            // Set required fields based on the selected user
            $email = $randomUser->getEmail();
            $nameParts = explode('@', $email);
            $res->setName(ucfirst($nameParts[0]));
            $res->setEmail($email);
            $res->setPhone('+216 ' . rand(20000000, 99999999));
            
            $manager->persist($res);
        }

        $manager->flush();
    }
}
