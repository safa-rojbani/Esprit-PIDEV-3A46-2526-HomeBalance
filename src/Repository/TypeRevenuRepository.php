<?php

namespace App\Repository;

use App\Entity\Family;
use App\Entity\TypeRevenu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TypeRevenu>
 */
class TypeRevenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TypeRevenu::class);
    }

    /**
     * @return array<int, string>
     */
    public function findDistinctNamesByFamily(Family $family): array
    {
        $rows = $this->createQueryBuilder('tr')
            ->select('DISTINCT tr.nomType AS nom')
            ->andWhere('tr.family = :family')
            ->setParameter('family', $family)
            ->orderBy('tr.nomType', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn (array $row): ?string => isset($row['nom']) ? trim((string) $row['nom']) : null,
            $rows
        )));
    }
}
