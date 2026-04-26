<?php

declare(strict_types=1);

namespace App\Crm\Transport\EventListener;

use App\Crm\Domain\Entity\Employee;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;

use function trim;

final class CrmEmployeeEntityEventListener
{
    public function prePersist(LifecycleEventArgs $event): void
    {
        $this->hydrateMissingNamesFromUser($event->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $event): void
    {
        $entity = $event->getObject();
        if (!$entity instanceof Employee) {
            return;
        }

        $beforeFirstName = $entity->getFirstName();
        $beforeLastName = $entity->getLastName();

        $this->hydrateMissingNamesFromUser($entity);

        if ($beforeFirstName === $entity->getFirstName() && $beforeLastName === $entity->getLastName()) {
            return;
        }

        $objectManager = $event->getObjectManager();
        if (!$objectManager instanceof EntityManagerInterface) {
            return;
        }

        $unitOfWork = $objectManager->getUnitOfWork();
        $metadata = $objectManager->getClassMetadata(Employee::class);
        $unitOfWork->recomputeSingleEntityChangeSet($metadata, $entity);
    }

    private function hydrateMissingNamesFromUser(object $entity): void
    {
        if (!$entity instanceof Employee) {
            return;
        }

        $user = $entity->getUser();
        if (!$user instanceof User) {
            return;
        }

        if ($this->isBlank($entity->getFirstName()) && !$this->isBlank($user->getFirstName())) {
            $entity->setFirstName($user->getFirstName());
        }

        if ($this->isBlank($entity->getLastName()) && !$this->isBlank($user->getLastName())) {
            $entity->setLastName($user->getLastName());
        }
    }

    private function isBlank(string $value): bool
    {
        return trim($value) === '';
    }
}
