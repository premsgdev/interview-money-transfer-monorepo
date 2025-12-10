<?php

namespace App\Factory;

use App\Entity\User;
use App\Message\UserDeletedEvent;
use App\Message\UserUpdatedEvent;

class UserMessageFactory
{
    public function createUserUpdateMessage(User $user): UserUpdatedEvent
    {
        return new UserUpdatedEvent(
            $user->getUuid(),
            $user->getEmail(),
            $user->getRoles()
        );
    }

    public function createUserDeleteMessage(User $user): UserDeletedEvent
    {
        return new UserDeletedEvent(
            $user->getUuid(),
            $user->getEmail(),
            $user->getRoles()
        );
    }
}
