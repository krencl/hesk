<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testEveryoneIsAUserAndRolesAreAddedAndRemovedWithoutDuplicates(): void
    {
        $user = new User('g1', 'a@example.com');
        self::assertSame(['ROLE_USER'], $user->getRoles());

        $user->addRole(User::ROLE_GAMES_ADMIN);
        $user->addRole(User::ROLE_GAMES_ADMIN);
        $user->addRole('ROLE_USER');
        self::assertSame(['ROLE_USER', 'ROLE_GAMES_ADMIN'], $user->getRoles());

        $user->removeRole(User::ROLE_GAMES_ADMIN);
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }
}
