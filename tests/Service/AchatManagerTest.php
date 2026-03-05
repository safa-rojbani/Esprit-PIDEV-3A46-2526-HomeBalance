<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Achat;
use App\Service\AchatManager;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AchatManagerTest extends TestCase
{
    #[Test]
    public function validateAcceptsValidAchat(): void
    {
        $achat = (new Achat())
            ->setNomArticle('Lait')
            ->setEstAchete(false)
            ->setCreatedAt(new DateTimeImmutable('-1 day'));

        $manager = new AchatManager();

        $manager->validate($achat);

        self::assertTrue(true);
    }

    #[Test]
    public function validateThrowsWhenCreatedAtIsInFuture(): void
    {
        $achat = (new Achat())
            ->setNomArticle('Pain')
            ->setEstAchete(false)
            ->setCreatedAt(new DateTimeImmutable('+2 hours'));

        $manager = new AchatManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Achat createdAt cannot be in the future.');

        $manager->validate($achat);
    }

    #[Test]
    public function validateThrowsWhenPurchasedItemHasNoCategory(): void
    {
        $achat = (new Achat())
            ->setNomArticle('Fromage')
            ->setEstAchete(true)
            ->setCreatedAt(new DateTimeImmutable('-2 hours'));

        $manager = new AchatManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A purchased item must have a category.');

        $manager->validate($achat);
    }
}
