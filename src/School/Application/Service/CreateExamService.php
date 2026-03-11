<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityCreated;
use App\School\Domain\Entity\Exam;
use App\School\Infrastructure\Repository\SchoolClassRepository;
use App\School\Infrastructure\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateExamService
{
    public function __construct(
        private SchoolClassRepository $classRepository,
        private TeacherRepository $teacherRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function create(string $title, ?string $classId, ?string $teacherId): Exam
    {
        $exam = (new Exam())->setTitle($title);
        if (is_string($classId)) {
            $exam->setSchoolClass($this->classRepository->find($classId));
        }
        if (is_string($teacherId)) {
            $exam->setTeacher($this->teacherRepository->find($teacherId));
        }

        $this->entityManager->persist($exam);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('school_exam', $exam->getId()));

        return $exam;
    }
}
