<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityCreated;
use App\School\Application\Exception\SchoolRelationException;
use App\School\Domain\Entity\School;
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
        if (!is_string($schoolId)) {
            throw SchoolRelationException::unprocessable('schoolId is required');
        }

        $school = $this->schoolRepository->find($schoolId);
        if (!$school instanceof School) {
            throw SchoolRelationException::notFound('schoolId');
        }

        $class = (new SchoolClass())->setName($name);
        $class->setSchool($school);

        $this->entityManager->persist($class);
        $this->entityManager->flush();
        $applicationSlug = $school->getApplication()?->getSlug();
        $this->messageBus->dispatch(new EntityCreated('school_class', $class->getId(), context: [
            'applicationSlug' => $applicationSlug,
        ]));

        return $class;
    }
}
