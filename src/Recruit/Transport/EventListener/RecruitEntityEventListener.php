<?php

declare(strict_types=1);

namespace App\Recruit\Transport\EventListener;

use App\Recruit\Domain\Entity\Job;
use App\User\Application\Security\UserTypeIdentification;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class RecruitEntityEventListener
{
    public function __construct(
        private readonly UserTypeIdentification $userTypeIdentification,
    ) {
    }

    public function prePersist(LifecycleEventArgs $event): void
    {
        $this->process($event);
    }

    public function preUpdate(LifecycleEventArgs $event): void
    {
        $this->process($event);
    }

    private function process(LifecycleEventArgs $event): void
    {
        $entity = $event->getObject();

        if ($entity instanceof Job) {
            if ($entity->getOwner() === null) {
                $entity->setOwner($this->userTypeIdentification->getUser());
            }

            $entity->ensureGeneratedSlug();
        }
    }
}
