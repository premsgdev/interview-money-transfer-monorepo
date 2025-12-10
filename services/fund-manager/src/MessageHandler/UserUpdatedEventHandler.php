<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\UserUpdatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UserUpdatedEventHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(UserUpdatedEvent $event): void
    {
        $repo = $this->em->getRepository(User::class);
        $user = $repo->find($event->userUuid);

        if (!$user) {
            $user = new User();
            $user->setUuid($event->userUuid);
            $this->em->persist($user);
        }

        $user->setEmail($event->email);
        $user->setRoles($event->roles);
        $user->setActive(true);

        $this->em->flush();
    }
}
