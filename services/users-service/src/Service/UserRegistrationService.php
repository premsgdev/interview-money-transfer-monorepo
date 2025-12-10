<?php

namespace App\Service;

use App\Entity\User;
use App\Factory\UserMessageFactory;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserRegistrationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private MessageBusInterface $bus,
        private UserMessageFactory $messageFactory,
    ) {
    }

    /**
     * @throws \DomainException if email already exists
     */
    public function register(string $email, string $plainPassword): User
    {
        $existing = $this->userRepository->findOneBy(['email' => $email]);
        if ($existing) {
            throw new \DomainException('Email already registered');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $plainPassword)
        );
        $user->setRoles(['ROLE_USER']);

        $this->em->persist($user);
        $this->em->flush();

        $envelope = $this->messageFactory->createUserUpdateMessage($user);
        $this->bus->dispatch($envelope);

        return $user;
    }
}
