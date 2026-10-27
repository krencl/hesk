<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Domain\Exception\DomainException;
use App\Entity\Game;
use App\Entity\Party;
use App\Entity\User;
use App\Enum\PartyRole;
use App\Enum\ScoreType;
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

    public function testGamesAreLinkedToThePartyOnceAndCanBeRemoved(): void
    {
        $party = Party::create('Hrajeme', new User('g1', 'a@example.com'));
        $game = new Game('Azul', ScoreType::MostPoints);

        self::assertFalse($party->hasGame($game));
        $link = $party->addGame($game);
        self::assertSame($link, $party->addGame($game), 'podruhé se nic nepřidá');
        self::assertTrue($party->hasGame($game));
        self::assertSame([$link], $party->getGameLinks()->toArray());

        $party->removeGame($game);
        self::assertFalse($party->hasGame($game));
        self::assertNull($party->findGameLink($game));
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

    public function testOnlyAdminsAndWritersCanWrite(): void
    {
        $admin = new User('g1', 'a@example.com');
        $writer = new User('g2', 'b@example.com');
        $member = new User('g3', 'c@example.com');
        $party = Party::create('Hrajeme', $admin);
        $party->addMember($writer, PartyRole::Writer);
        $party->addMember($member);

        self::assertSame([true, true, false], [$party->canWrite($admin), $party->canWrite($writer), $party->canWrite($member)]);
        self::assertFalse($party->canWrite(new User('g4', 'd@example.com')), 'a stranger cannot write');
        self::assertTrue($party->isMember($member));
        self::assertFalse($party->isAdmin($writer));
    }

    public function testLastAdminCannotBeDemotedToWriter(): void
    {
        $creator = new User('g1', 'a@example.com');
        $party = Party::create('Hrajeme', $creator);

        $this->expectException(DomainException::class);
        $party->changeRole($creator, PartyRole::Writer);
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

    /** @return iterable<string, array{string, string}> */
    public static function equivalentNames(): iterable
    {
        yield 'velikost písmen' => ['Honza', 'honza'];
        yield 'diakritika a velikost' => ['Čeněk', 'čeněk'];
        yield 'jen diakritika' => ['Šimon', 'Simon'];
        yield 'opačně' => ['Simon', 'Šimon'];
        yield 'okolní mezery' => ['Eva', ' EVA '];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('equivalentNames')]
    public function testNamesDifferingOnlyInCaseOrAccentsCollideLikeInTheDatabase(string $existing, string $new): void
    {
        $party = Party::create('Hrajeme', new User('g1', 'a@example.com'));
        $guest = $party->addGuest($existing);

        self::assertTrue($party->isPlayerNameTaken($new));
        self::assertFalse($party->isPlayerNameTaken($new, $guest), 'sám sebe hráč neblokuje');
        try {
            $party->addGuest($new);
            self::fail('addGuest musí duplicitu odmítnout');
        } catch (DomainException) {
        }
        $other = $party->addGuest('Jiný');
        $this->expectException(DomainException::class);
        $party->renamePlayer($other, $new);
    }

    public function testGuestCanBeRenamedToAnotherSpellingOfItsOwnName(): void
    {
        $party = Party::create('Hrajeme', new User('g1', 'a@example.com'));
        $guest = $party->addGuest('Simon');

        $party->renamePlayer($guest, 'Šimon');

        self::assertSame('Šimon', $guest->getName());
    }

    public function testMemberPlayerNameAvoidsAccentInsensitiveCollisions(): void
    {
        $creator = new User('g1', 'a@example.com');
        $creator->setNickname('Anna');
        $party = Party::create('Hrajeme', $creator);
        $party->addGuest('Šimon');
        $simon = new User('g2', 'b@example.com');
        $simon->setNickname('Simon');

        $party->addMember($simon);

        self::assertSame('Simon 2', $party->findPlayerFor($simon)?->getName());
    }

    public function testSyncPlayerNameRespectsAccentInsensitiveCollisions(): void
    {
        $user = new User('g1', 'a@example.com');
        $party = Party::create('Hrajeme', $user);
        $party->addGuest('Šimon');

        $user->setNickname('Simon');
        $party->syncPlayerName($user);

        self::assertSame('a', $party->findPlayerFor($user)?->getName(), 'jméno je obsazené, hráč zůstává');
    }

    public function testUpdateGuestRenamesAndTogglesVisibilityIndependently(): void
    {
        $party = Party::create('Hrajeme', new User('g1', 'a@example.com'));
        $guest = $party->addGuest('Bob');

        $party->updateGuest($guest, ' Bobík ', null);
        self::assertSame(['Bobík', true], [$guest->getName(), $guest->isVisible()]);

        $party->updateGuest($guest, null, false);
        self::assertSame(['Bobík', false], [$guest->getName(), $guest->isVisible()], 'jméno zůstane');

        $party->updateGuest($guest, 'Bobík', true);
        self::assertSame(['Bobík', true], [$guest->getName(), $guest->isVisible()]);
    }

    /** @return iterable<string, array{?string, ?bool, string}> */
    public static function invalidGuestUpdates(): iterable
    {
        yield 'není co měnit' => [null, null, 'Není co měnit.'];
        yield 'prázdné jméno' => ['  ', null, 'Jméno nesmí být prázdné.'];
        yield 'obsazené jméno' => ['cyril', true, 'Hráč s tímto jménem už v partě existuje.'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidGuestUpdates')]
    public function testInvalidGuestUpdatesAreRejectedWithoutChanges(?string $name, ?bool $visible, string $message): void
    {
        $party = Party::create('Hrajeme', new User('g1', 'a@example.com'));
        $guest = $party->addGuest('Bob');
        $party->addGuest('Cyril');

        try {
            $party->updateGuest($guest, $name, $visible);
            self::fail('Úprava se musí odmítnout.');
        } catch (DomainException $e) {
            self::assertSame($message, $e->getMessage());
        }
        self::assertSame(['Bob', true], [$guest->getName(), $guest->isVisible()]);
    }

    public function testMemberWithAccountCannotBeUpdatedAsGuest(): void
    {
        $creator = new User('g1', 'a@example.com');
        $party = Party::create('Hrajeme', $creator);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Člena s účtem tu upravit nejde');
        $party->updateGuest($party->findPlayerFor($creator), null, false);
    }

    public function testMemberCanBeFoundByUserId(): void
    {
        $creator = new User('g1', 'a@example.com');
        $other = new User('g2', 'b@example.com');
        (new \ReflectionProperty($creator, 'id'))->setValue($creator, 11);
        (new \ReflectionProperty($other, 'id'))->setValue($other, 22);
        $party = Party::create('Hrajeme', $creator);
        $party->addMember($other);

        self::assertSame($other, $party->findMemberByUserId(22)?->getUser());
        self::assertSame($creator, $party->findMemberByUserId(11)?->getUser());
        self::assertNull($party->findMemberByUserId(33));
    }

    public function testMemberNicknameRenamesPlayerAndOverridesTheAccountNickname(): void
    {
        $user = new User('g1', 'a@example.com');
        $user->setNickname('Anna');
        $party = Party::create('Hrajeme', $user);
        $party->addGuest('Taken');

        $party->changeMemberNickname($user, '  Anička ');
        self::assertSame('Anička', $party->findPlayerFor($user)?->getName());
        self::assertSame('Anička', $party->findMember($user)?->displayName());

        // změna přezdívky účtu nepřebije nick v partě
        $user->setNickname('Anna K.');
        $party->syncPlayerName($user);
        self::assertSame('Anička', $party->findPlayerFor($user)?->getName());

        // prázdný nick = zpět na přezdívku účtu
        $party->changeMemberNickname($user, '');
        self::assertNull($party->findMember($user)?->getNickname());
        self::assertSame('Anna K.', $party->findPlayerFor($user)?->getName());

        $this->expectException(DomainException::class);
        $party->changeMemberNickname($user, 'taken');
    }

    public function testMemberNicknameIsUsedForTheNewPlayerOfAMember(): void
    {
        $owner = new User('g1', 'a@example.com');
        $owner->setNickname('Anna');
        $party = Party::create('Hrajeme', $owner);
        $bob = new User('g2', 'b@example.com');
        $bob->setNickname('Bob');
        $party->addMember($bob);
        $party->changeMemberNickname($bob, 'Bobík');
        self::assertSame('Bobík', $party->findPlayerFor($bob)?->getName());
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
