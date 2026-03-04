<?php

namespace App\Repository;

use App\Entity\ModuleEvenement\InvitationRsvp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<InvitationRsvp>
 */
class InvitationRsvpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvitationRsvp::class);
    }
}
