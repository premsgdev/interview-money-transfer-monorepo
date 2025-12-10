<?php

namespace App\Dto;

class TransferRequest
{
    public string $fromAccountUuid;
    public string $toAccountUuid;
    public string $amount;
    public string $currency;

    public function __construct(string $fromAccountUuid, string $toAccountUuid, string $amount, string $currency)
    {
        $this->fromAccountUuid = $fromAccountUuid;
        $this->toAccountUuid = $toAccountUuid;
        $this->amount = $amount;
        $this->currency = $currency;
    }
}
