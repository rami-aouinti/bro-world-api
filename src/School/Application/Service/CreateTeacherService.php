<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityCreated;
use App\School\Application\Exception\SchoolRelationException;
use App\School\Domain\Entity\Teacher;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateTeacherService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private UserRepository $userRepository,
    ) {
    }

    public function create(string $userId): Teacher
    {
        $user = $this->userRepository->find($userId);
        if (!$user instanceof User) {
            throw SchoolRelationException::notFound('userId');
        }

        $teacher = (new Teacher())->setUser($user);

        $this->entityManager->persist($teacher);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('school_teacher', $teacher->getId()));

        return $teacher;
    }
}
