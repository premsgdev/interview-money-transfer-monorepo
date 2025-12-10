<?php

namespace App\Tests\Functional;

use App\Entity\Account;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUser;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TransferControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Clean tables (order matters due to FK constraints)
        $conn = $this->em->getConnection();
        $platform = $conn->getDatabasePlatform();

        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['transfer', 'account', 'user_projection'] as $table) {
            if ($platform->supportsSchemas()) {
                // Not needed for MySQL, but harmless
            }
            $conn->executeStatement(sprintf('TRUNCATE TABLE %s', $table));
        }
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_successful_transfer_debits_and_credits_accounts(): void
    {
        // 1) Arrange: create projection user and accounts
        $user = new User();
        $userUuid = '11111111-1111-1111-1111-111111111111';
        $user->setUuid($userUuid);
        $user->setEmail('alice@example.com');
        $user->setRoles(['ROLE_USER']);
        $user->setActive(true);
        $this->em->persist($user);

        $accountFrom = new Account();
        $accountFrom->setUser($user);
        $accountFrom->setBalance('1000.00');
        $accountFrom->setCurrency('INR');

        $accountTo = new Account();
        $accountTo->setUser($user);
        $accountTo->setBalance('500.00');
        $accountTo->setCurrency('INR');

        $this->em->persist($accountFrom);
        $this->em->persist($accountTo);
        $this->em->flush();

        $fromUuid = $accountFrom->getAccountUuid();
        $toUuid   = $accountTo->getAccountUuid();

        // 2) Arrange: generate a real JWT for this user email
        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        $jwtUser = new JWTUser($user->getEmail(), $user->getRoles());
        $token = $jwtManager->create($jwtUser);

        // 3) Act: call the transfer endpoint
        $client = static::createClient();
        $payload = [
            'fromAccountUuid' => $fromUuid,
            'toAccountUuid'   => $toUuid,
            'amount'          => '250.00',
            'currency'        => 'INR',
        ];

        $client->request(
            'POST',
            '/api/transfers',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'HTTP_Idempotency-Key' => 'test-transfer-1',
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($payload)
        );

        $response = $client->getResponse();
        $this->assertSame(201, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('transferUuid', $data);
        $this->assertSame('250.00', $data['amount']);
        $this->assertSame('INR', $data['currency']);
        $this->assertSame($fromUuid, $data['fromAccountUuid']);
        $this->assertSame($toUuid, $data['toAccountUuid']);

        // 4) Assert: balances updated correctly in DB
        $this->em->clear();

        $repo = $this->em->getRepository(Account::class);
        /** @var Account $updatedFrom */
        $updatedFrom = $repo->findOneBy(['accountUuid' => $fromUuid]);
        /** @var Account $updatedTo */
        $updatedTo = $repo->findOneBy(['accountUuid' => $toUuid]);

        $this->assertSame('750.00', $updatedFrom->getBalance());
        $this->assertSame('750.00', $updatedTo->getBalance());
    }

    public function test_transfer_fails_with_insufficient_balance(): void
    {
        // Arrange user and accounts
        $user = new User();
        $userUuid = '22222222-2222-2222-2222-222222222222';
        $user->setUuid($userUuid);
        $user->setEmail('bob@example.com');
        $user->setRoles(['ROLE_USER']);
        $user->setActive(true);
        $this->em->persist($user);

        $accountFrom = new Account();
        $accountFrom->setUser($user);
        $accountFrom->setBalance('50.00'); // low balance
        $accountFrom->setCurrency('INR');

        $accountTo = new Account();
        $accountTo->setUser($user);
        $accountTo->setBalance('100.00');
        $accountTo->setCurrency('INR');

        $this->em->persist($accountFrom);
        $this->em->persist($accountTo);
        $this->em->flush();

        $fromUuid = $accountFrom->getAccountUuid();
        $toUuid   = $accountTo->getAccountUuid();

        /** @var JWTTokenManagerInterface $jwtManager */
        $jwtManager = static::getContainer()->get(JWTTokenManagerInterface::class);
        $jwtUser = new JWTUser($user->getEmail(), $user->getRoles());
        $token = $jwtManager->create($jwtUser);

        $client = static::createClient();
        $payload = [
            'fromAccountUuid' => $fromUuid,
            'toAccountUuid'   => $toUuid,
            'amount'          => '250.00', // more than balance
            'currency'        => 'INR',
        ];

        $client->request(
            'POST',
            '/api/transfers',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'HTTP_Idempotency-Key' => 'test-transfer-2',
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($payload)
        );

        $response = $client->getResponse();
        $this->assertSame(422, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('Insufficient balance', $data['error']);

        // balances unchanged
        $this->em->clear();
        $repo = $this->em->getRepository(Account::class);
        /** @var Account $updatedFrom */
        $updatedFrom = $repo->findOneBy(['accountUuid' => $fromUuid]);
        /** @var Account $updatedTo */
        $updatedTo = $repo->findOneBy(['accountUuid' => $toUuid]);

        $this->assertSame('50.00', $updatedFrom->getBalance());
        $this->assertSame('100.00', $updatedTo->getBalance());
    }
}
