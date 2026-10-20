<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Domain\Exception\DomainException;
use App\Entity\Party;
use App\Entity\User;
use App\Enum\PartyRole;
use PHPUnit\Framework\TestCase;

final class PartyTest extends TestCase
{
    public function testCreatorBecomesAdmin(): void
    {
        $creator = new User('g1', 'a@example.com');
        $party = Party::create('Hrajeme', $creator);

        self::assertTrue($party->isAdmin($creator));
        self::assertCount(1, $party->getMembers());
    }

    public function testShortNameIsGeneratedFromTheNameAndCanBeChanged(): void
    {
        $party = Party::create('Viki a Marek', new User('g1', 'a@example.com'));
        self::assertSame('VM', $party->getShortName());

        $party->update(' Pátek ', ' PTK ');
        self::assertSame(['Pátek', 'PTK'], [$party->getName(), $party->getShortName()]);
    }

    public function testAddMemberIsIdempotent(): void
    {
        $creator = new User('g1', 'a@example.com');
        $other = new User('g2', 'b@example.com');
        $party = Party::create('Hrajeme', $creator);

        $party->addMember($other);
        $party->addMember($other);

        self::assertCount(2, $party->getMembers());
        self::assertTrue($party->isMember($other));
        self::assertFalse($party->isAdmin($other));
    }

    public function testLastAdminCannotBeRemovedOrDemoted(): void
    {
        $creator = new User('g1', 'a@example.com');
        $party = Party::create('Hrajeme', $creator);
        $party->addMember(new User('g2', 'b@example.com'));

        try {
            $party->removeMember($creator);
            self::fail('Expected exception');
        } catch (DomainException) {
        }
        $this->expectException(DomainException::class);
        $party->changeRole($creator, PartyRole::Member);
    }

    public function testAdminCanLeaveWhenAnotherAdminExists(): void
    {
        $creator = new User('g1', 'a@example.com');
        $other = new User('g2', 'b@example.com');
        $party = Party::create('Hrajeme', $creator);
        $party->addMember($other, PartyRole::Admin);

        $party->removeMember($creator);

        self::assertFalse($party->isMember($creator));
        self::assertTrue($party->isAdmin($other));
    }

    public function testRemovingNonMemberFails(): void
    {
        $party = Party::create('Hrajeme', new User('g1', 'a@example.com'));

        $this->expectException(DomainException::class);
        $party->removeMember(new User('g9', 'z@example.com'));
    }

    public function testMembersGetPlayersAndLeavingKeepsThemAsGuests(): void
    {
        $creator = new User('g1', 'a@example.com');
        $creator->setNickname('Anna');
        $other = new User('g2', 'b@example.com');
        $other->setNickname('Anna');
        $party = Party::create('Hrajeme', $creator);

        $party->addMember($other);

        self::assertSame(['Anna', 'Anna 2'], array_values(array_map(static fn ($p) => $p->getName(), $party->getPlayers()->toArray())));
        $player = $party->findPlayerFor($other);
        $party->removeMember($other);
        self::assertNull($player?->getUser());
        self::assertCount(2, $party->getPlayers());
    }

    public function testGuestNamesAreUniqueCaseInsensitively(): void
    {
        $party = Party::create('Hrajeme', new User('g1', 'a@example.com'));
        $party->addGuest('Honza');

        $this->expectException(DomainException::class);
        $party->addGuest(' honza ');
    }

    public function testSyncPlayerNameFollowsNicknameUnlessTaken(): void
    {
        $user = new User('g1', 'a@example.com');
        $party = Party::create('Hrajeme', $user);
        $party->addGuest('Taken');

        $user->setNickname('Taken');
        $party->syncPlayerName($user);
        self::assertSame('a', $party->findPlayerFor($user)?->getName());

        $user->setNickname('Free');
        $party->syncPlayerName($user);
        self::assertSame('Free', $party->findPlayerFor($user)?->getName());
    }
}
