<?php

namespace App\Repository;

use App\Entity\UserActivityPattern;
use App\Entity\User;
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

    /**
     * Find or create pattern for a user.
     */
    public function findOrCreateForUser(User $user): UserActivityPattern
    {
        $pattern = $this->findOneBy(['user' => $user]);
        
        if (!$pattern) {
            $pattern = new UserActivityPattern();
            $pattern->setUser($user);
            $this->getEntityManager()->persist($pattern);
        }
        
        return $pattern;
    }
}
