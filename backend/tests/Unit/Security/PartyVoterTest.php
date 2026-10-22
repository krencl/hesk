<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Enum\PartyRole;
use App\Security\PartyVoter;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class PartyVoterTest extends TestCase
{
    /** @return iterable<string, array{?PartyRole, bool, bool, bool}> role → smí číst, smí zapisovat, smí spravovat */
    public static function roles(): iterable
    {
        yield 'správce' => [PartyRole::Admin, true, true, true];
        yield 'zapisovač' => [PartyRole::Writer, true, true, false];
        yield 'člen' => [PartyRole::Member, true, false, false];
        yield 'cizí' => [null, false, false, false];
    }

    #[DataProvider('roles')]
    public function testPermissionsFollowTheRole(?PartyRole $role, bool $view, bool $write, bool $admin): void
    {
        $owner = Fixtures::user('Owner');
        $party = Fixtures::party($owner);
        $user = Fixtures::user('Tester');
        if (null !== $role) {
            Fixtures::member($party, $user, $role);
        }
        $token = new UsernamePasswordToken($user, 'main', []);
        $voter = new PartyVoter();

        foreach ([PartyVoter::VIEW => $view, PartyVoter::WRITE => $write, PartyVoter::ADMIN => $admin] as $attribute => $expected) {
            self::assertSame($expected ? VoterInterface::ACCESS_GRANTED : VoterInterface::ACCESS_DENIED, $voter->vote($token, $party, [$attribute]), $attribute);
        }
    }

    public function testAnonymousIsDeniedAndForeignSubjectsAreAbstained(): void
    {
        $party = Fixtures::party(Fixtures::user('Owner'));
        $voter = new PartyVoter();

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new NullToken(), $party, [PartyVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new UsernamePasswordToken(Fixtures::user('A'), 'main', []), new \stdClass(), [PartyVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new UsernamePasswordToken(Fixtures::user('A'), 'main', []), $party, ['JINE']));
    }
}
