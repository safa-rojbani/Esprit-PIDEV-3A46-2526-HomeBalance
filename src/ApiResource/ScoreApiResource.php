<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\State\ScoreApiProvider;

#[ApiResource(
    shortName: 'Score',
    operations: [
        new GetCollection(
            uriTemplate: '/scores',
            security: "is_granted('ROLE_USER')"
        ),
        new Get(
            uriTemplate: '/scores/{id}',
            security: "is_granted('ROLE_USER')"
        ),
    ],
    provider: ScoreApiProvider::class,
    paginationEnabled: false
)]
final class ScoreApiResource
{
    #[ApiProperty(identifier: true)]
    public int $id;
    public string $userId;
    public string $memberName;
    public int $totalPoints;
    public int $rank;
    public ?string $lastUpdated = null;
}

