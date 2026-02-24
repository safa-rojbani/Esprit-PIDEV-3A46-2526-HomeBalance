<?php

namespace App\Repository;

use App\Entity\UserPresence;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserPresence>
 */
class UserPresenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPresence::class);
    }

    /**
     * Find or create presence for a user.
     */
    public function findOrCreateForUser(User $user): UserPresence
    {
        $presence = $this->findOneBy(['user' => $user]);
        
        if (!$presence) {
            $presence = new UserPresence();
            $presence->setUser($user);
            $this->getEntityManager()->persist($presence);
        }
        
        return $presence;
    }

    /**
     * Find all offline users based on threshold.
     *
     * @return UserPresence[]
     */
    public function findOfflineUsers(int $offlineThresholdMinutes): array
    {
        $threshold = new \DateTimeImmutable("-{$offlineThresholdMinutes} minutes");
        
        return $this->createQueryBuilder('p')
            ->where('p.isOnline = :isOnline')
            ->orWhere('p.lastSeenAt < :threshold')
            ->setParameter('isOnline', false)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }
}
