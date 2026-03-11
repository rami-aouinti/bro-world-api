<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityCreated;
use App\School\Domain\Entity\Teacher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateTeacherService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function create(string $name): Teacher
    {
        $teacher = (new Teacher())->setName($name);

        $this->entityManager->persist($teacher);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('school_teacher', $teacher->getId()));

        return $teacher;
    }
}
