<?php

namespace App\Message;

final class UserDeletedEvent
{
    public function __construct(
        public string $userUuid,
        public string $email,
        public array $roles = [],
    ) {
    }
}
