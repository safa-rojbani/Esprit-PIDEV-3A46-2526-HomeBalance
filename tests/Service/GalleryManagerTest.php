<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Gallery;
use App\Enum\EtatGallery;
use App\Service\GalleryManager;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GalleryManagerTest extends TestCase
{
    #[Test]
    public function validateAcceptsValidGallery(): void
    {
        $gallery = (new Gallery())
            ->setName('Vacances')
            ->setDescription('Photos de vacances')
            ->setEtat(EtatGallery::ACTIF)
            ->setCreatedAt(new DateTimeImmutable('-1 day'));

        $manager = new GalleryManager();

        $manager->validate($gallery);

        self::assertTrue(true);
    }

    #[Test]
    public function validateThrowsWhenCreatedAtIsInFuture(): void
    {
        $gallery = (new Gallery())
            ->setName('Famille')
            ->setDescription('Album famille')
            ->setEtat(EtatGallery::HIDDEN)
            ->setCreatedAt(new DateTimeImmutable('+1 day'));

        $manager = new GalleryManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Gallery createdAt cannot be in the future.');

        $manager->validate($gallery);
    }

    #[Test]
    public function validateThrowsWhenDeletedGalleryHasNoDeletedAt(): void
    {
        $gallery = (new Gallery())
            ->setName('Archive')
            ->setDescription('Album archive')
            ->setEtat(EtatGallery::DELETED)
            ->setCreatedAt(new DateTimeImmutable('-2 days'))
            ->setDeletedAt(null);

        $manager = new GalleryManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A deleted gallery must define deletedAt.');

        $manager->validate($gallery);
    }
}
