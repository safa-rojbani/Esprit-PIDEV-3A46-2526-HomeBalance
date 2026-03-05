<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Evenement;
use App\Service\EvenementManager;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EvenementManagerTest extends TestCase
{
    #[Test]
    public function validateAcceptsValidEvenement(): void
    {
        $evenement = (new Evenement())
            ->setTitre('Reunion familiale')
            ->setDescription('Point hebdomadaire de coordination')
            ->setLieu('Salon')
            ->setDateDebut(new DateTimeImmutable('+1 day 10:00'))
            ->setDateFin(new DateTimeImmutable('+1 day 12:00'))
            ->setShareWithFamily(false);

        $manager = new EvenementManager();

        $manager->validate($evenement);

        self::assertTrue(true);
    }

    #[Test]
    public function validateThrowsWhenDateFinIsBeforeOrEqualDateDebut(): void
    {
        $evenement = (new Evenement())
            ->setTitre('Sortie')
            ->setDescription('Sortie en famille')
            ->setLieu('Parc')
            ->setDateDebut(new DateTimeImmutable('+2 days 15:00'))
            ->setDateFin(new DateTimeImmutable('+2 days 14:00'))
            ->setShareWithFamily(false);

        $manager = new EvenementManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event end date must be after start date.');

        $manager->validate($evenement);
    }

    #[Test]
    public function validateThrowsWhenSharedEventHasNoFamily(): void
    {
        $evenement = (new Evenement())
            ->setTitre('Anniversaire')
            ->setDescription('Organisation anniversaire')
            ->setLieu('Maison')
            ->setDateDebut(new DateTimeImmutable('+3 days 16:00'))
            ->setDateFin(new DateTimeImmutable('+3 days 18:00'))
            ->setShareWithFamily(true);

        $manager = new EvenementManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A shared event must be linked to a family.');

        $manager->validate($evenement);
    }
}
