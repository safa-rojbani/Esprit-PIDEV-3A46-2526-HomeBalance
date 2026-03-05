<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\FamilyRole;
use App\Enum\SystemRole;
use App\Enum\UserStatus;
use DateTime;
use DateTimeImmutable;
use LogicException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string> List of changed field names
     */
    public function updateAccountProfile(User $user, array $data): array
    {
        $changes = [];

        $locale = $data['language'] ?? null;
        $locale = $locale !== null && $locale !== '' ? $locale : ($user->getLocale() ?? 'en');

        $timeZone = $data['timeZones'] ?? null;
        $timeZone = $timeZone !== null && $timeZone !== '' ? $timeZone : ($user->getTimeZone() ?? 'UTC');

        $changes = array_merge($changes, $this->updateIfChanged(
            $user->getFirstName(),
            $data['firstName'] ?? $user->getFirstName() ?? '',
            function ($value) use ($user): void {
                $user->setFirstName($value ?? '');
            },
            'firstName'
        ));
        $changes = array_merge($changes, $this->updateIfChanged(
            $user->getLastName(),
            $data['lastName'] ?? $user->getLastName() ?? '',
            function ($value) use ($user): void {
                $user->setLastName($value ?? '');
            },
            'lastName'
        ));
        $changes = array_merge($changes, $this->updateIfChanged(
            $user->getEmail(),
            $data['email'] ?? $user->getEmail() ?? '',
            function ($value) use ($user): void {
                $user->setEmail($value ?? '');
            },
            'email'
        ));

        if ($locale !== $user->getLocale()) {
            $user->setLocale($locale);
            $changes[] = 'locale';
        }

        if ($timeZone !== $user->getTimeZone()) {
            $user->setTimeZone($timeZone);
            $changes[] = 'timeZone';
        }

        $preferences = $user->getPreferences() ?? [];
        $profile = $preferences['profile'] ?? [];
        $preferences['profile'] = [
            'organization' => $data['organization'] ?? ($profile['organization'] ?? null),
            'phoneNumber' => $data['phoneNumber'] ?? ($profile['phoneNumber'] ?? null),
            'address' => $data['address'] ?? ($profile['address'] ?? null),
            'state' => $data['state'] ?? ($profile['state'] ?? null),
            'zipCode' => $data['zipCode'] ?? ($profile['zipCode'] ?? null),
            'country' => $data['country'] ?? ($profile['country'] ?? null),
            'currency' => ($data['currency'] ?? $profile['currency'] ?? 'usd') ?: 'usd',
        ];

        foreach ($preferences['profile'] as $key => $value) {
            if (($profile[$key] ?? null) !== $value) {
                $changes[] = 'profile.' . $key;
            }
        }

        $user->setPreferences($preferences);
        $user->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return array_values(array_unique($changes));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function registerUser(array $data): User
    {
        $user = new User();
        $user
            ->setUsername($data['username'])
            ->setEmail($data['email'])
            ->setFirstName($data['first_name'])
            ->setLastName($data['last_name'])
            ->setLocale('en')
            ->setTimeZone('UTC')
            ->setStatus(UserStatus::ACTIVE)
            ->setSystemRole(SystemRole::CUSTOMER)
            ->setFamilyRole(FamilyRole::SOLO)
            ->setBirthDate(new DateTime('2000-01-01'))
            ->setPreferences(['profile' => ['organization' => null]]);

        $hashed = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashed);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        $isValid = $this->passwordHasher->isPasswordValid($user, $currentPassword);
        if (!$isValid) {
            throw new LogicException('Current password is incorrect.');
        }

        $hashed = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashed);
        $user->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();
    }

    /**
     * @param callable(mixed): void $setter
     * @return list<string>
     */
    private function updateIfChanged(mixed $current, mixed $newValue, callable $setter, string $field): array
    {
        if ($newValue === $current) {
            return [];
        }

        $setter($newValue);

        return [$field];
    }
}
