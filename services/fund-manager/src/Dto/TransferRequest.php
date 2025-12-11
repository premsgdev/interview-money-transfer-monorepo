<?php

namespace App\Dto;

class TransferRequest
{
    public function __construct(
        public string $fromAccountUuid, 
        public string $toAccountUuid, 
        public string $amount, 
        public string $currency
        ) {
    }
}
