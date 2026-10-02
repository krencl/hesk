<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Party;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Party> */
final class PartyVoter extends Voter
{
    public const VIEW = 'PARTY_VIEW';
    public const ADMIN = 'PARTY_ADMIN';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::ADMIN], true) && $subject instanceof Party;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return self::ADMIN === $attribute ? $subject->isAdmin($user) : $subject->isMember($user);
    }
}
