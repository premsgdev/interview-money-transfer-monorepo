<?php

namespace App\Factory;

use App\Entity\User;
use App\Message\UserUpdateMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;

class UserMessageFactory
{
    public function createUserUpdateMessage(User $user): Envelope
    {
        $message = new UserUpdateMessage(
            $user->getUuid(),
            $user->getEmail(),
            $user->getRoles()
        );

        $stamp = new AmqpStamp(
            routingKey: 'user_update',  
            flags: AMQP_NOPARAM,
            attributes: ['type' => 'user_update']
        );

        return new Envelope($message, [$stamp]);
    }

    public function createUserDeleteMessage(User $user): Envelope
    {
        $message = new UserUpdateMessage(
            $user->getUuid(),
            $user->getEmail(),
            $user->getRoles()
        );

        $stamp = new AmqpStamp(
            routingKey: 'user_update',
            flags: AMQP_NOPARAM,
            attributes: ['type' => 'user_delete']
        );

        return new Envelope($message, [$stamp]);
    }
}
