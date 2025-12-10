<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\UserDeletedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UserDeletedEventHandler
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(UserDeletedEvent $event): void
    {
        $repo = $this->em->getRepository(User::class);
        $user = $repo->find($event->userUuid);

        if ($user) {
            $user->setActive(false);
            $this->em->flush();
        }
    }
}
