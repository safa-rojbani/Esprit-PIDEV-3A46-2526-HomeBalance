<?php

namespace App\Repository;

use App\Entity\Evenement;
use App\Entity\Family;
use App\Entity\TypeEvenement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    /**
     * @return Evenement[]
     */
    public function findVisibleForUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $family = $user->getFamily();

        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.dateDebut', 'ASC');
        if ($family === null) {
            $qb->andWhere('e.family IS NULL');
        } else {
            $qb->andWhere('(e.family = :family OR e.family IS NULL)')
                ->setParameter('family', $family);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Evenement[]
     */
    public function findForFamilyWithFilters(Family $family, ?TypeEvenement $type, ?string $search): array
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.dateDebut', 'ASC');

        $qb->andWhere('(e.family = :family OR e.family IS NULL)')
            ->setParameter('family', $family);

        if ($type !== null) {
            $qb->andWhere('e.TypeEvenement = :type')
                ->setParameter('type', $type);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('e.titre LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Evenement[]
     */
    public function findWithFilters(Family $family, ?TypeEvenement $type, ?string $search): array
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.dateDebut', 'ASC');

        $qb->andWhere('e.family = :family')
            ->setParameter('family', $family);

        if ($type !== null) {
            $qb->andWhere('e.TypeEvenement = :type')
                ->setParameter('type', $type);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('e.titre LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Evenement[]
     */
    public function findAdminDefaultsWithFilters(User $admin, ?TypeEvenement $type, ?string $search): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.family IS NULL')
            ->andWhere('e.createdBy = :admin')
            ->setParameter('admin', $admin)
            ->orderBy('e.dateDebut', 'ASC');

        if ($type !== null) {
            $qb->andWhere('e.TypeEvenement = :type')
                ->setParameter('type', $type);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('e.titre LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findForFamilyWithFiltersQueryBuilder(Family $family, ?TypeEvenement $type, ?string $search)
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.dateDebut', 'ASC');

        $qb->andWhere('(e.family = :family OR e.family IS NULL)')
            ->setParameter('family', $family);

        if ($type !== null) {
            $qb->andWhere('e.TypeEvenement = :type')
                ->setParameter('type', $type);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('e.titre LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb;
    }

    public function findAdminDefaultsQueryBuilder(User $admin, ?TypeEvenement $type, ?string $search)
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.family IS NULL')
            ->andWhere('e.createdBy = :admin')
            ->setParameter('admin', $admin)
            ->orderBy('e.dateDebut', 'ASC');

        if ($type !== null) {
            $qb->andWhere('e.TypeEvenement = :type')
                ->setParameter('type', $type);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('e.titre LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb;
    }

    /**
     * @return array<int, array{month: int, total: string}>
     */
    public function countByMonthForAdmin(User $admin, int $year): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = <<<SQL
SELECT MONTH(e.date_debut) AS month, COUNT(e.id) AS total
FROM evenement e
WHERE e.family_id IS NULL
  AND e.created_by_id = :adminId
  AND YEAR(e.date_debut) = :year
GROUP BY month
ORDER BY month ASC
SQL;

        return $conn->executeQuery($sql, [
            'adminId' => $admin->getId(),
            'year' => $year,
        ])->fetchAllAssociative();
    }

    /**
     * @return array<int, array{label: string, total: string}>
     */
    public function countByTypeForAdmin(User $admin): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = <<<SQL
SELECT t.nom AS label, COUNT(e.id) AS total
FROM evenement e
INNER JOIN type_evenement t ON t.id = e.type_evenement_id
WHERE e.family_id IS NULL
  AND e.created_by_id = :adminId
GROUP BY t.id
ORDER BY total DESC
SQL;

        return $conn->executeQuery($sql, [
            'adminId' => $admin->getId(),
        ])->fetchAllAssociative();
    }

    /**
     * @return array<int, array{month: int, total: string}>
     */
    public function countByMonthForFamily(Family $family, int $year): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = <<<SQL
SELECT MONTH(e.date_debut) AS month, COUNT(e.id) AS total
FROM evenement e
WHERE (e.family_id = :familyId OR e.family_id IS NULL)
  AND YEAR(e.date_debut) = :year
GROUP BY month
ORDER BY month ASC
SQL;

        return $conn->executeQuery($sql, [
            'familyId' => $family->getId(),
            'year' => $year,
        ])->fetchAllAssociative();
    }

    /**
     * @return array<int, array{label: string, total: string}>
     */
    public function countByTypeForFamily(Family $family): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = <<<SQL
SELECT t.nom AS label, COUNT(e.id) AS total
FROM evenement e
INNER JOIN type_evenement t ON t.id = e.type_evenement_id
WHERE (e.family_id = :familyId OR e.family_id IS NULL)
GROUP BY t.id
ORDER BY total DESC
SQL;

        return $conn->executeQuery($sql, [
            'familyId' => $family->getId(),
        ])->fetchAllAssociative();
    }

    /**
     * @return Evenement[]
     */
    public function findConflictsForFamily(Family $family, \DateTimeInterface $start, \DateTimeInterface $end, ?int $excludeId = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.family = :family')
            ->andWhere('e.dateDebut <= :endDate')
            ->andWhere('COALESCE(e.dateFin, e.dateDebut) >= :startDate')
            ->setParameter('family', $family)
            ->setParameter('startDate', $start)
            ->setParameter('endDate', $end)
            ->orderBy('e.dateDebut', 'ASC');

        if ($excludeId !== null) {
            $qb->andWhere('e.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Evenement[]
     */
    public function findConflictsForAdmin(User $admin, \DateTimeInterface $start, \DateTimeInterface $end, ?int $excludeId = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.family IS NULL')
            ->andWhere('e.createdBy = :admin')
            ->andWhere('e.dateDebut <= :endDate')
            ->andWhere('COALESCE(e.dateFin, e.dateDebut) >= :startDate')
            ->setParameter('admin', $admin)
            ->setParameter('startDate', $start)
            ->setParameter('endDate', $end)
            ->orderBy('e.dateDebut', 'ASC');

        if ($excludeId !== null) {
            $qb->andWhere('e.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return Evenement[] Returns an array of Evenement objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Evenement
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
