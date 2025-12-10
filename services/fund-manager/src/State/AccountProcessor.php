<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Account;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class AccountProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
    ) {
    }

    /**
     * @param Account $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $userPrincipal = $this->security->getUser();
        if (!$userPrincipal) {
            throw new \RuntimeException('Authentication required');
        }

        $email = $userPrincipal->getUserIdentifier();

        /** @var User|null $projectionUser */
        $projectionUser = $this->em->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if (!$projectionUser || !$projectionUser->isActive()) {
            throw new \RuntimeException('User not found in projection');
        }

        if (!$data instanceof Account) {
            return $data;
        }

        // New accounts: set owner & timestamps
        if (null === $data->getId()) {
            $data->setUser($projectionUser);
        }

        $data->touch();
        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }
}
