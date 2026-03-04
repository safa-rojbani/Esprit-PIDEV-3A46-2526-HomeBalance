<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserActivityPattern;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserActivityPattern>
 */
class UserActivityPatternRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserActivityPattern::class);
    }

    public function findOrCreateForUser(User $user): UserActivityPattern
    {
        $pattern = $this->findOneBy(['user' => $user]);

        if ($pattern instanceof UserActivityPattern) {
            return $pattern;
        }

        $pattern = new UserActivityPattern();
        $pattern->setUser($user);
        $pattern->setPeakHours([]);

        $this->getEntityManager()->persist($pattern);

        return $pattern;
    }
}

