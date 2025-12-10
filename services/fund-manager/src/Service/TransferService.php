<?php

namespace App\Service;

use App\Dto\TransferRequest;
use App\Entity\Account;
use App\Entity\Transfer;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TransferService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws \RuntimeException|\DomainException for business errors
     */
    public function transfer(TransferRequest $dto, string $initiatorEmail, ?string $idempotencyKey = null): Transfer
    {
        // Idempotency
        if ($idempotencyKey) {
            $cacheKey = 'transfer_idem_'.$idempotencyKey;
            $alreadyProcessed = $this->cache->get($cacheKey, function (ItemInterface $item) {
                $item->expiresAfter(60);

                return false;
            });

            if (true === $alreadyProcessed) {
                throw new \RuntimeException('Duplicate transfer request (idempotency key)');
            }
        }

        $userRepo = $this->em->getRepository(User::class);
        $accountRepo = $this->em->getRepository(Account::class);

        /** @var User|null $initiatorUser */
        $initiatorUser = $userRepo->findOneBy(['email' => $initiatorEmail]);
        if (!$initiatorUser || !$initiatorUser->isActive()) {
            throw new \DomainException('Initiating user not found or inactive');
        }

        if ($dto->fromAccountUuid === $dto->toAccountUuid) {
            throw new \DomainException('Cannot transfer to the same account');
        }

        if (!is_numeric($dto->amount) || (float) $dto->amount <= 0) {
            throw new \DomainException('Amount must be a positive number');
        }

        $amount = number_format((float) $dto->amount, 2, '.', '');

        $conn = $this->em->getConnection();

        return $conn->transactional(function () use (
            $dto,
            $amount,
            $accountRepo,
            $initiatorUser,
            $idempotencyKey
        ) {
            // Load and lock accounts
            /** @var Account|null $from */
            $from = $accountRepo->findOneBy(['accountUuid' => $dto->fromAccountUuid]);
            /** @var Account|null $to */
            $to = $accountRepo->findOneBy(['accountUuid' => $dto->toAccountUuid]);

            if (!$from || !$to) {
                throw new \DomainException('One or both accounts not found');
            }

            // Pessimistic locks
            $this->em->lock($from, LockMode::PESSIMISTIC_WRITE);
            $this->em->lock($to, LockMode::PESSIMISTIC_WRITE);

            if ($from->getCurrency() !== $dto->currency || $to->getCurrency() !== $dto->currency) {
                throw new \DomainException('Currency mismatch between accounts and transfer request');
            }

            // Ownership: initiator must own "from" account
            if ($from->getUser()->getUuid() !== $initiatorUser->getUuid()) {
                throw new \DomainException('You are not allowed to transfer funds from this account');
            }

            if ((float) $from->getBalance() < (float) $amount) {
                throw new \DomainException('Insufficient balance');
            }

            // Apply balances
            $from->setBalance(number_format((float) $from->getBalance() - (float) $amount, 2, '.', ''))
                 ->touch();
            $to->setBalance(number_format((float) $to->getBalance() + (float) $amount, 2, '.', ''))
               ->touch();

            // Create transfer record
            $transfer = new Transfer(
                $from,
                $to,
                $amount,
                $dto->currency,
                $initiatorUser
            );

            $this->em->persist($transfer);
            $this->em->flush();

            // Mark idempotency key as used
            if ($idempotencyKey) {
                $cacheKey = 'transfer_idem_'.$idempotencyKey;
                $this->cache->delete($cacheKey);
                $this->cache->get($cacheKey, function (ItemInterface $item) {
                    $item->expiresAfter(60);

                    return true;
                });
            }

            $this->logger->info('Transfer completed', [
                'transferUuid' => $transfer->getTransferUuid(),
                'from' => $from->getAccountUuid(),
                'to' => $to->getAccountUuid(),
                'amount' => $amount,
                'currency' => $dto->currency,
                'initiatorUserUuid' => $initiatorUser->getUuid(),
            ]);

            return $transfer;
        });
    }
}
