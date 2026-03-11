<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityDeleted;
use App\School\Domain\Entity\SchoolClass;
use App\School\Infrastructure\Repository\SchoolClassRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DeleteClassService
{
    public function __construct(
        private SchoolClassRepository $classRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function delete(string $id): bool
    {
        $class = $this->classRepository->find($id);
        if (!$class instanceof SchoolClass) {
            return false;
        }

        $this->entityManager->remove($class);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('school_class', $id));

        return true;
    }
}
