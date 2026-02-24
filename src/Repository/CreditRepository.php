<?php

namespace App\Repository;

use App\Entity\Credit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Credit>
 */
class CreditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Credit::class);
    }

    /**
     * @return array<int, Credit>
     */
    public function search(string $query): array
    {
        $term = trim($query);
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC');

        if ($term !== '') {
            $qb->andWhere('LOWER(c.title) LIKE :q')
                ->setParameter('q', '%'.strtolower($term).'%');
        }

        return $qb->getQuery()->getResult();
    }
}
