<?php
namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function findPaginated(bool $onlyUpcoming = true, int $page = 1, int $limit = 9, string $search = '', string $status = ''): array
    {
        $qb = $this->createQueryBuilder('e');
        $countQb = $this->createQueryBuilder('e')->select('COUNT(e.id)');

        $now = new \DateTimeImmutable();

        // Status filter takes priority over onlyUpcoming
        if ($status === 'passed') {
            $qb->andWhere('e.endDate < :now')->setParameter('now', $now);
            $countQb->andWhere('e.endDate < :now')->setParameter('now', $now);
        } elseif ($status === 'ongoing') {
            $qb->andWhere('e.date <= :now AND e.endDate >= :now')->setParameter('now', $now);
            $countQb->andWhere('e.date <= :now AND e.endDate >= :now')->setParameter('now', $now);
        } elseif ($status === 'upcoming') {
            $qb->andWhere('e.date > :now')->setParameter('now', $now);
            $countQb->andWhere('e.date > :now')->setParameter('now', $now);
        } elseif ($onlyUpcoming) {
            // Default for public: show ongoing + upcoming
            $qb->andWhere('e.endDate >= :now')->setParameter('now', $now);
            $countQb->andWhere('e.endDate >= :now')->setParameter('now', $now);
        }

        // Search filter
        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $qb->andWhere('LOWER(e.title) LIKE :search OR LOWER(e.location) LIKE :search OR LOWER(e.description) LIKE :search')
               ->setParameter('search', '%' . $searchLower . '%');
            $countQb->andWhere('LOWER(e.title) LIKE :search OR LOWER(e.location) LIKE :search OR LOWER(e.description) LIKE :search')
                    ->setParameter('search', '%' . $searchLower . '%');
        }

        // Upcoming sorts ASC (closest first), Past/All sorts DESC (newest first)
        $qb->orderBy('e.date', $onlyUpcoming ? 'ASC' : 'DESC');

        $total = $countQb->getQuery()->getSingleScalarResult();

        $results = $qb->setFirstResult(($page - 1) * $limit)
                      ->setMaxResults($limit)
                      ->getQuery()
                      ->getResult();

        return [
            'data' => $results,
            'total' => (int) $total
        ];
    }
}
