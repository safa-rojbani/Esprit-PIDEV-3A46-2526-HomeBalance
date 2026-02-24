<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\State\TaskApiProvider;

#[ApiResource(
    shortName: 'Task',
    operations: [
        new GetCollection(
            uriTemplate: '/tasks',
            security: "is_granted('ROLE_USER')"
        ),
        new Get(
            uriTemplate: '/tasks/{id}',
            security: "is_granted('ROLE_USER')"
        ),
    ],
    provider: TaskApiProvider::class,
    paginationEnabled: false
)]
final class TaskApiResource
{
    #[ApiProperty(identifier: true)]
    public int $id;
    public string $title;
    public string $description;
    public string $difficulty;
    public string $recurrence;
    public bool $isActive;
    public string $scope;
    public int $points;
    public ?string $createdAt = null;
}

