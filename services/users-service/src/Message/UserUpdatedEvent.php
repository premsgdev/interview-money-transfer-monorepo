<?php

namespace App\Message;

final class UserUpdatedEvent
{
    public function __construct(
        public string $userUuid,
        public string $email,
        public array $roles = [],
    ) {
    }
}
