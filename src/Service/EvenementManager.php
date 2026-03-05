<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Evenement;
use InvalidArgumentException;

final class EvenementManager
{
    /**
     * Validate Evenement business rules.
     *
     * Rules:
     * 1) dateFin must be strictly after dateDebut.
     * 2) shareWithFamily=true requires family to be set.
     */
    public function validate(Evenement $evenement): void
    {
        $dateDebut = $evenement->getDateDebut();
        $dateFin = $evenement->getDateFin();

        if ($dateDebut === null || $dateFin === null) {
            throw new InvalidArgumentException('Event start date and end date are required.');
        }

        if ($dateFin <= $dateDebut) {
            throw new InvalidArgumentException('Event end date must be after start date.');
        }

        if ($evenement->isShareWithFamily() && $evenement->getFamily() === null) {
            throw new InvalidArgumentException('A shared event must be linked to a family.');
        }
    }
}
