<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\UserEntityManager;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserEntityManagerTest extends TestCase
{
    #[Test]
    public function validateAcceptsValidUser(): void
    {
        $user = (new User())
            ->setCreatedAt(new DateTimeImmutable('-1 day'))
            ->setResetToken(null)
            ->setResetExpiresAt(null);

        $manager = new UserEntityManager();

        $manager->validate($user);

        self::assertTrue(true);
    }

    #[Test]
    public function validateThrowsWhenCreatedAtIsInFuture(): void
    {
        $user = (new User())
            ->setCreatedAt(new DateTimeImmutable('+1 day'));

        $manager = new UserEntityManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User createdAt cannot be in the future.');

        $manager->validate($user);
    }

    #[Test]
    public function validateThrowsWhenResetTokenHasNoExpiration(): void
    {
        $user = (new User())
            ->setCreatedAt(new DateTimeImmutable('-1 day'))
            ->setResetToken('token-123')
            ->setResetExpiresAt(null);

        $manager = new UserEntityManager();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('resetExpiresAt is required when resetToken is set.');

        $manager->validate($user);
    }
}
