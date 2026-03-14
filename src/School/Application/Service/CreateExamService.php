<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityCreated;
use App\School\Application\Exception\SchoolRelationException;
use App\School\Domain\Entity\Exam;
use App\School\Domain\Entity\School;
use App\School\Domain\Entity\SchoolClass;
use App\School\Domain\Entity\Teacher;
use App\School\Domain\Enum\ExamStatus;
use App\School\Domain\Enum\ExamType;
use App\School\Domain\Enum\Term;
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

    public function create(
        School $school,
        string $title,
        ?string $classId,
        ?string $teacherId,
        ExamType $type,
        ExamStatus $status,
        Term $term,
    ): Exam {
        if (!is_string($classId)) {
            throw SchoolRelationException::unprocessable('classId is required');
        }

        if (!is_string($teacherId)) {
            throw SchoolRelationException::unprocessable('teacherId is required');
        }

        $class = $this->classRepository->find($classId);
        if (!$class instanceof SchoolClass || $class->getSchool()?->getId() !== $school->getId()) {
            throw SchoolRelationException::notFound('classId');
        }

        $teacher = $this->teacherRepository->find($teacherId);
        if (!$teacher instanceof Teacher) {
            throw SchoolRelationException::notFound('teacherId');
        }

        $teacherBelongsToSchool = false;
        foreach ($teacher->getClasses() as $teacherClass) {
            if ($teacherClass->getSchool()?->getId() === $school->getId()) {
                $teacherBelongsToSchool = true;
                break;
            }
        }

        if (!$teacherBelongsToSchool) {
            throw SchoolRelationException::notFound('teacherId');
        }

        if (!$teacher->getClasses()->contains($class)) {
            throw SchoolRelationException::unprocessable('teacherId is not assigned to classId');
        }

        $exam = (new Exam())
            ->setTitle($title)
            ->setType($type)
            ->setStatus($status)
            ->setTerm($term)
            ->setSchoolClass($class)
            ->setTeacher($teacher);

        $this->entityManager->persist($exam);
        $this->entityManager->flush();
        $applicationSlug = $class->getSchool()?->getApplication()?->getSlug();
        $this->messageBus->dispatch(new EntityCreated('school_exam', $exam->getId(), context: [
            'applicationSlug' => $applicationSlug,
        ]));

        return $exam;
    }
}
