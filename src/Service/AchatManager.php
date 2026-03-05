<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Achat;
use DateTimeImmutable;
use InvalidArgumentException;

final class AchatManager
{
    /**
     * Validate Achat business rules.
     *
     * Rules:
     * 1) createdAt must not be in the future.
     * 2) If an item is marked as purchased, a category is required.
     */
    public function validate(Achat $achat): void
    {
        $name = trim((string) $achat->getNomArticle());
        if ($name === '') {
            throw new InvalidArgumentException('Achat item name is required.');
        }

        $createdAt = $achat->getCreatedAt();
        if ($createdAt === null) {
            throw new InvalidArgumentException('Achat createdAt is required.');
        }

        if ($createdAt > new DateTimeImmutable()) {
            throw new InvalidArgumentException('Achat createdAt cannot be in the future.');
        }

        if ($achat->isEstAchete() === true && $achat->getCategorie() === null) {
            throw new InvalidArgumentException('A purchased item must have a category.');
        }
    }
}
