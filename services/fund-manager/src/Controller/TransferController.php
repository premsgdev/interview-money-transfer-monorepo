<?php

namespace App\Controller;

use App\Dto\TransferRequest;
use App\Service\TransferService;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TransferController extends AbstractController
{
    public function __construct(
        private TransferService $transferService,
    ) {
    }

    #[Route('/api/transfers', name: 'api_transfer', methods: ['POST'])]
    public function transfer(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Authentication required'], 401);
        }

        $initiatorEmail = $user->getUserIdentifier();

        $data = json_decode($request->getContent(), true) ?? [];

        $fromAccountUuid = $data['fromAccountUuid'] ?? null;
        $toAccountUuid = $data['toAccountUuid'] ?? null;
        $amount = $data['amount'] ?? null;
        $currency = $data['currency'] ?? 'INR';

        if (!$fromAccountUuid || !$toAccountUuid || !$amount) {
            return new JsonResponse([
                'error' => 'fromAccountUuid, toAccountUuid and amount are required',
            ], 400);
        }

        $idempotencyKey = $request->headers->get('Idempotency-Key');

        try {
            $dto = new TransferRequest($fromAccountUuid, $toAccountUuid, (string) $amount, $currency);
            $transfer = $this->transferService->transfer($dto, $initiatorEmail, $idempotencyKey);

            return new JsonResponse([
                'transferUuid' => $transfer->getTransferUuid(),
                'fromAccountUuid' => $transfer->getFromAccount()->getAccountUuid(),
                'toAccountUuid' => $transfer->getToAccount()->getAccountUuid(),
                'amount' => $transfer->getAmount(),
                'currency' => $transfer->getCurrency(),
                'status' => 'completed',
            ], 201);
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (DBALException $e) {
            return new JsonResponse(['error' => 'database error'], 500);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'unexpected error'], 500);
        }
    }
}
