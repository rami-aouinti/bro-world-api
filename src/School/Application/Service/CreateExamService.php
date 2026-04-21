<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityCreated;
use App\School\Application\Exception\SchoolRelationException;
use App\School\Domain\Entity\Exam;
use App\School\Domain\Entity\School;
use App\School\Domain\Enum\ExamStatus;
use App\School\Domain\Enum\ExamType;
use App\School\Domain\Enum\Term;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateExamService
{
    public function __construct(
        private SchoolReferenceResolver $referenceResolver,
        private SchoolExamQuizProvisioningService $schoolExamQuizProvisioningService,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function create(
        School $school,
        string $title,
        ?string $classId,
        ?string $courseId,
        ?string $teacherId,
        ExamType $type,
        ExamStatus $status,
        Term $term,
    ): Exam {
        $class = $this->referenceResolver->resolveClassInSchool($school, $classId);
        $course = $this->referenceResolver->resolveCourseInSchool($school, $courseId);
        $teacher = $this->referenceResolver->resolveTeacherInSchool($school, $teacherId);

        if ($course->getSchoolClass()?->getId() !== $class->getId()) {
            throw SchoolRelationException::unprocessable('courseId must belong to classId');
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
            ->setCourse($course)
            ->setTeacher($teacher);

        $this->entityManager->persist($exam);

        if ($type === ExamType::QUIZ) {
            $this->schoolExamQuizProvisioningService->provision($exam);
        }

        $this->entityManager->flush();
        $applicationSlug = $class->getSchool()?->getApplication()?->getSlug();
        $this->messageBus->dispatch(new EntityCreated('school_exam', $exam->getId(), context: [
            'applicationSlug' => $applicationSlug,
        ]));

        return $exam;
    }
}
