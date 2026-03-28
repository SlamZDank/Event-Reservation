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
        // 1. make sys users
        $admin = new User();
        $admin->setEmail('admin@event.com');
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        $mainUser = new User();
        $mainUser->setEmail('user@event.com');
        $mainUser->setPassword($this->hasher->hashPassword($mainUser, 'user123'));
        $manager->persist($mainUser);

        // 2. make test users
        $users = [$mainUser];
        for ($i = 1; $i <= 60; $i++) {
            $u = new User();
            $u->setEmail("student{$i}@issat.com");
            $u->setPassword($this->hasher->hashPassword($u, 'password'));
            $manager->persist($u);
            $users[] = $u;
        }

        // 3. make 60 events
        $events = [];
        $topics = ['AI Summit', 'React Workshop', 'Symfony Days', 'Docker Masterclass', 'Cybersecurity Basics', 'Startup Pitch', 'Blockchain 101', 'Cloud Native Meetup', 'Python Bootcamp', 'UX Design Session'];
        $locations = ['ISSAT Amphitheater', 'Sousse Tech Hub', 'Online (Zoom)', 'Sousse Novotel', 'Campus Library'];

        for ($i = 1; $i <= 60; $i++) {
            $event = new Event();
            
            $topic = $topics[array_rand($topics)];
            $event->setTitle($topic . ' ' . $i);
            $event->setDescription("Join us for an exciting day exploring topics around {$topic}. Bring your laptop and questions!");
            
            // dates span -30d to +180d
            $daysOffset = rand(-30, 180);
            $date = new \DateTimeImmutable();
            $date = $date->modify(($daysOffset >= 0 ? '+' : '') . $daysOffset . ' days');
            $date = $date->setTime(rand(9, 17), 0);
            
            $event->setDate($date);
            
            // end 1-8h later
            $durationHours = rand(1, 8);
            $event->setEndDate($date->modify("+{$durationHours} hours"));
            
            $event->setLocation($locations[array_rand($locations)]);
            
            // seats 5 to 150
            $event->setSeats(rand(5, 150));
            
            $manager->persist($event);
            $events[] = $event;
        }

        // 4. make 60 random books
        // 1 book per event
        for ($i = 0; $i < 60; $i++) {
            $event = $events[$i];
            
            // pick random user
            $randomUser = $users[array_rand($users)];
            
            $res = new Reservation();
            $res->setEvent($event);
            $res->setUser($randomUser);
            
            // set user fields
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
