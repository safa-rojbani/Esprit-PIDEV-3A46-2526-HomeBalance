<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Gallery;
use App\Enum\EtatGallery;
use DateTimeImmutable;
use InvalidArgumentException;

final class GalleryManager
{
    /**
     * Validate Gallery business rules.
     *
     * Rules:
     * 1) createdAt must not be in the future.
     * 2) A deleted gallery must have deletedAt set.
     */
    public function validate(Gallery $gallery): void
    {
        $name = trim((string) $gallery->getName());
        if ($name === '') {
            throw new InvalidArgumentException('Gallery name is required.');
        }

        $etat = $gallery->getEtat();
        if ($etat === null) {
            throw new InvalidArgumentException('Gallery status is required.');
        }

        $createdAt = $gallery->getCreatedAt();
        if ($createdAt !== null && $createdAt > new DateTimeImmutable()) {
            throw new InvalidArgumentException('Gallery createdAt cannot be in the future.');
        }

        if ($etat === EtatGallery::DELETED && $gallery->getDeletedAt() === null) {
            throw new InvalidArgumentException('A deleted gallery must define deletedAt.');
        }
    }
}
