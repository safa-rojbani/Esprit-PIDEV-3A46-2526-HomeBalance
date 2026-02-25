<?php

namespace App\Repository;

use App\Entity\Badge;
use App\Entity\Family;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function loadUserByIdentifier(string $identifier): ?User
    {
        $identifier = mb_strtolower($identifier);

        return $this->createQueryBuilder('u')
            ->where('LOWER(u.email) = :identifier OR LOWER(u.username) = :identifier')
            ->setParameter('identifier', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @deprecated since Symfony 5.3, use loadUserByIdentifier */
    public function loadUserByUsername(string $username): ?User
    {
        return $this->loadUserByIdentifier($username);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function adminSearchQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u');

        if ($filters['query']) {
            $qb->andWhere('LOWER(u.email) LIKE :query OR LOWER(u.username) LIKE :query OR LOWER(u.FirstName) LIKE :query OR LOWER(u.LastName) LIKE :query')
                ->setParameter('query', '%' . mb_strtolower($filters['query']) . '%');
        }

        if ($filters['systemRole']) {
            $qb->andWhere('u.systemRole = :systemRole')
                ->setParameter('systemRole', $filters['systemRole']);
        }

        if ($filters['status']) {
            $qb->andWhere('u.status = :status')
                ->setParameter('status', $filters['status']);
        }

        $allowedSorts = [
            'createdAt' => 'u.createdAt',
            'lastLogin' => 'u.lastLogin',
            'email' => 'u.email',
            'username' => 'u.username',
        ];
        $sort = $filters['sort'] ?? 'createdAt';
        $direction = strtoupper((string) ($filters['direction'] ?? 'DESC'));
        $direction = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC';
        $sortField = $allowedSorts[$sort] ?? $allowedSorts['createdAt'];

        return $qb->orderBy($sortField, $direction);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<User>
     */
    public function adminSearch(array $filters): array
    {
        return $this->adminSearchQueryBuilder($filters)
            ->setMaxResults(25)
            ->getQuery()
            ->getResult();
    }

    public function countUsersWithBadge(Badge $badge): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->innerJoin('u.badges', 'b')
            ->andWhere('b = :badge')
            ->setParameter('badge', $badge)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<User>
     */
    public function findFamilyMembers(Family $family): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.family = :family')
            ->setParameter('family', $family)
            ->orderBy('u.FirstName', 'ASC')
            ->addOrderBy('u.LastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
