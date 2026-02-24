<?php

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\TaskApiResource;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use App\Service\ActiveFamilyResolver;
use App\Service\TaskPointResolver;
use Symfony\Bundle\SecurityBundle\Security;

final class TaskApiProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveFamilyResolver $familyResolver,
        private readonly TaskRepository $taskRepository,
        private readonly TaskPointResolver $taskPointResolver,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $operation instanceof CollectionOperationInterface ? [] : null;
        }

        if ($operation instanceof CollectionOperationInterface) {
            $tasks = $this->resolveCollection($user);
            $pointsByTask = $this->taskPointResolver->resolvePointsForTasks($tasks);

            return array_map(
                fn (Task $task): TaskApiResource => $this->toResource($task, (int) ($pointsByTask[$task->getId()] ?? 0)),
                $tasks
            );
        }

        $taskId = (int) ($uriVariables['id'] ?? 0);
        if ($taskId <= 0) {
            return null;
        }

        $task = $this->taskRepository->find($taskId);
        if (!$task instanceof Task || !$this->canAccessTask($user, $task)) {
            return null;
        }

        return $this->toResource($task, $this->taskPointResolver->resolvePoints($task));
    }

    /**
     * @return array<int, Task>
     */
    private function resolveCollection(User $user): array
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return $this->taskRepository->findGlobalAdminTasks();
        }

        $family = $this->familyResolver->resolveForUser($user);
        if ($family === null) {
            return [];
        }

        return $this->taskRepository->findActiveFamilyTasksFiltered($family, '', 'newest');
    }

    private function canAccessTask(User $user, Task $task): bool
    {
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return $task->getFamily() === null;
        }

        $family = $this->familyResolver->resolveForUser($user);
        if ($family === null) {
            return false;
        }

        return $task->getFamily()?->getId() === $family->getId();
    }

    private function toResource(Task $task, int $points): TaskApiResource
    {
        $resource = new TaskApiResource();
        $resource->id = (int) $task->getId();
        $resource->title = (string) $task->getTitle();
        $resource->description = (string) $task->getDescription();
        $resource->difficulty = (string) ($task->getDifficulty()?->value ?? '');
        $resource->recurrence = (string) ($task->getRecurrence()?->value ?? '');
        $resource->isActive = (bool) $task->isActive();
        $resource->scope = $task->getFamily() === null ? 'global' : 'family';
        $resource->points = $points;
        $resource->createdAt = $task->getCreatedAt()?->format(\DateTimeInterface::ATOM);

        return $resource;
    }
}

