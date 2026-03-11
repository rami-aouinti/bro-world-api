<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityCreated;
use App\School\Domain\Entity\School;
use App\School\Domain\Entity\SchoolClass;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateClassByApplicationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function create(string $applicationSlug, School $school, string $name): SchoolClass
    {
        $class = (new SchoolClass())
            ->setSchool($school)
            ->setName($name);

        $this->entityManager->persist($class);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('school_class', $class->getId(), context: [
            'applicationSlug' => $applicationSlug,
        ]));

        return $class;
    }
}
