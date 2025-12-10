<?php

namespace App\Dto;

class AccountView
{
    public function __construct(
        public string $accountUuid,
        public string $currency,
        public string $balance,
        public string $userUuid,
    ) {
    }
}
