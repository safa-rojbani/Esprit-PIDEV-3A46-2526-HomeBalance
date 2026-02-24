<?php

namespace App\DTO;

use DateTimeInterface;

final class AuditEvent
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $type,
        public readonly DateTimeInterface $occurredAt,
    ) {
    }
}
