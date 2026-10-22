<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Game;
use App\Entity\Party;
use App\Entity\Player;
use App\Entity\User;
use App\Enum\PartyRole;
use App\Enum\ScoreType;

/** Entity v paměti bez databáze; ID, která by jinak přidělila DB, se nastavují ručně (viz `id()`). */
final class Fixtures
{
    private static int $next = 1;

    /** Nastaví entitě `id` (jako by ji uložila DB); bez zadaného čísla vezme další z řady. */
    public static function id(object $entity, ?int $id = null): object
    {
        $property = new \ReflectionProperty($entity, 'id');
        if (null === $id && null !== $property->getValue($entity)) {
            return $entity;
        }
        $property->setValue($entity, $id ?? self::$next++);

        return $entity;
    }

    public static function user(string $nickname, ?string $email = null): User
    {
        $user = new User('g-'.$nickname, $email ?? strtolower($nickname).'@example.com');
        $user->setNickname($nickname);

        return self::id($user);
    }

    /** Parta se zakladatelem (admin) a se všemi hráči/členy, kterým se přidělí ID. */
    public static function party(User $creator, string $name = 'Pátek'): Party
    {
        return self::persist(Party::create($name, $creator));
    }

    /** Přidá člena s rolí (a jeho hráče); vše dostane ID. */
    public static function member(Party $party, User $user, PartyRole $role = PartyRole::Member): User
    {
        $party->addMember($user, $role);
        self::persist($party);

        return $user;
    }

    public static function guest(Party $party, string $name): Player
    {
        $guest = $party->addGuest($name);
        self::persist($party);

        return $guest;
    }

    public static function player(Party $party, User $user): Player
    {
        return $party->findPlayerFor($user) ?? throw new \LogicException('Uživatel není v partě.');
    }

    public static function game(Party $party, string $name = 'Azul', ScoreType $type = ScoreType::MostPoints): Game
    {
        return self::id(new Game($party, $name, $type));
    }

    /** Přidělí ID party, jejím členům, hráčům a nedotčeným vazbám. */
    public static function persist(Party $party): Party
    {
        self::id($party);
        foreach ($party->getMembers() as $member) {
            self::id($member);
            self::id($member->getUser());
        }
        foreach ($party->getPlayers() as $player) {
            self::id($player);
        }

        return $party;
    }
}
