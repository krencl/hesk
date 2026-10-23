<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Domain\Exception\DomainException;
use App\Entity\Party;
use App\Entity\PartyGame;
use App\Entity\Player;
use App\Entity\User;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

/** Vlastní hráči hry v partě (`PartyGame::syncPlayers`); hráči party i hosté jsou jen v paměti. */
final class PartyGameTest extends TestCase
{
    private User $anna;
    private Party $party;
    private PartyGame $link;

    protected function setUp(): void
    {
        $this->anna = Fixtures::user('Anna');
        $this->party = Fixtures::party($this->anna);
        $this->link = $this->party->addGame(Fixtures::catalogGame('Coop'));
    }

    /** @param list<array{id: ?int, name: string}> $items */
    private function sync(array $items): void
    {
        $this->link->syncPlayers($items);
        Fixtures::persist($this->party); // nové hráče by uložila DB, tady dostanou id
    }

    /** @return list<string> */
    private function names(): array
    {
        return array_values(array_map(static fn (Player $p): string => $p->getName(), $this->link->getPlayers()->toArray()));
    }

    /** @return list<string> */
    private function partyNames(): array
    {
        return array_values(array_map(static fn (Player $p): string => $p->getName(), $this->party->getPlayers()->toArray()));
    }

    public function testNewNamesBecomeGuestsOfThePartyAndPlayersOfTheGame(): void
    {
        $this->sync([['id' => null, 'name' => ' Boss '], ['id' => null, 'name' => 'Minion']]);

        self::assertSame(['Boss', 'Minion'], $this->names());
        self::assertSame(['Anna', 'Boss', 'Minion'], $this->partyNames());
        self::assertSame([false, false], array_map(static fn (Player $p): bool => null !== $p->getUser(), $this->link->getPlayers()->toArray()));
    }

    public function testNameWithoutIdMatchesAnExistingPlayerOfThePartyCaseAndAccentInsensitively(): void
    {
        $cenek = Fixtures::guest($this->party, 'Čeněk');

        $this->sync([['id' => null, 'name' => 'cenek'], ['id' => null, 'name' => 'ANNA']]);

        self::assertSame([$cenek, Fixtures::player($this->party, $this->anna)], array_values($this->link->getPlayers()->toArray()));
        self::assertSame(['Anna', 'Čeněk'], $this->partyNames(), 'žádný nový host nevznikl');
        self::assertSame('Čeněk', $cenek->getName(), 'jméno existujícího hráče se nemění');
    }

    public function testBlankAndRepeatedNamesAreDroppedIgnoringCaseAndAccents(): void
    {
        $this->sync([
            ['id' => null, 'name' => 'Šimon'],
            ['id' => null, 'name' => 'simon'],
            ['id' => null, 'name' => ' SIMON '],
            ['id' => null, 'name' => '   '],
            ['id' => null, 'name' => ''],
            ['id' => null, 'name' => 'Eva'],
        ]);

        self::assertSame(['Šimon', 'Eva'], $this->names());
        self::assertSame(['Anna', 'Šimon', 'Eva'], $this->partyNames());
    }

    public function testPlayerWithIdIsRenamedInsteadOfCreatedAgain(): void
    {
        $boss = Fixtures::guest($this->party, 'Boss');
        $this->link->getPlayers()->add($boss);

        $this->sync([['id' => $boss->getId(), 'name' => 'Velký Boss']]);

        self::assertSame([$boss], array_values($this->link->getPlayers()->toArray()));
        self::assertSame('Velký Boss', $boss->getName());
        self::assertSame(['Anna', 'Velký Boss'], $this->partyNames());
    }

    public function testRenameCannotTakeANameOfAnotherPlayerOfTheParty(): void
    {
        $boss = Fixtures::guest($this->party, 'Boss');
        Fixtures::guest($this->party, 'Šimon');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('už v partě existuje');
        $this->sync([['id' => $boss->getId(), 'name' => 'simon']]);
    }

    public function testPlayerOfAnotherPartyIsRejected(): void
    {
        $eve = Fixtures::user('Eve');
        $foreign = Fixtures::player(Fixtures::party($eve, 'Cizí'), $eve);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Hráč nepatří do této party.');
        $this->sync([['id' => $foreign->getId(), 'name' => $foreign->getName()]]);
    }

    public function testNameLongerThan100CharactersIsRejectedAndNothingChanges(): void
    {
        $boss = Fixtures::guest($this->party, 'Boss');
        $this->link->getPlayers()->add($boss);

        try {
            $this->sync([['id' => null, 'name' => 'Minion'], ['id' => null, 'name' => str_repeat('x', 101)]]);
            self::fail('Příliš dlouhé jméno se musí odmítnout.');
        } catch (DomainException $e) {
            self::assertStringContainsString('100 znaků', $e->getMessage());
        }

        self::assertSame(['Boss'], $this->names(), 'hráči hry zůstali');
        self::assertSame(['Anna', 'Boss'], $this->partyNames(), 'host Minion nevznikl');
        $this->sync([['id' => null, 'name' => str_repeat('x', 100)]]);
        self::assertSame([str_repeat('x', 100)], $this->names(), '100 znaků je v pořádku');
    }

    public function testPlayersMissingFromTheListStopBeingGamePlayersButStayInTheParty(): void
    {
        $this->sync([['id' => null, 'name' => 'Boss'], ['id' => null, 'name' => 'Minion']]);

        $this->sync([['id' => null, 'name' => 'minion']]);

        self::assertSame(['Minion'], $this->names());
        self::assertSame(['Anna', 'Boss', 'Minion'], $this->partyNames());

        $this->sync([]);
        self::assertSame([], $this->names());
    }

    public function testSyncIsIdempotent(): void
    {
        $items = [['id' => null, 'name' => 'Boss'], ['id' => null, 'name' => 'Minion']];
        $this->sync($items);
        $first = array_values($this->link->getPlayers()->toArray());

        $this->sync($items);

        self::assertSame($first, array_values($this->link->getPlayers()->toArray()));
        self::assertSame(['Anna', 'Boss', 'Minion'], $this->partyNames());
    }
}
