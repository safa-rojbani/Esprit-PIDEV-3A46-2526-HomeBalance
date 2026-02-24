<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\State\ScoreHistoryApiProvider;

#[ApiResource(
    shortName: 'ScoreHistory',
    operations: [
        new GetCollection(
            uriTemplate: '/score-history',
            security: "is_granted('ROLE_USER')"
        ),
        new Get(
            uriTemplate: '/score-history/{id}',
            security: "is_granted('ROLE_USER')"
        ),
    ],
    provider: ScoreHistoryApiProvider::class,
    paginationEnabled: false
)]
final class ScoreHistoryApiResource
{
    #[ApiProperty(identifier: true)]
    public int $id;
    public int $points;
    public string $taskTitle;
    public string $memberName;
    public bool $awardedByAi = false;
    public string $source = 'manual';
    public ?string $createdAt = null;
}
