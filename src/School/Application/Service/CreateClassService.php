<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityCreated;
use App\School\Domain\Entity\SchoolClass;
use App\School\Infrastructure\Repository\SchoolRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateClassService
{
    public function __construct(
        private SchoolRepository $schoolRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function create(string $name, ?string $schoolId): SchoolClass
    {
        $class = (new SchoolClass())->setName($name);
        if (is_string($schoolId)) {
            $class->setSchool($this->schoolRepository->find($schoolId));
        }

        $this->entityManager->persist($class);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('school_class', $class->getId()));

        return $class;
    }
}
