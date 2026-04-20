<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityCreated;
use App\School\Application\Exception\SchoolRelationException;
use App\School\Domain\Entity\Course;
use App\School\Domain\Entity\School;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateCourseService
{
    public function __construct(
        private SchoolReferenceResolver $referenceResolver,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @param list<array{url: string, originalName: string, mimeType: string, size: int, extension: string}> $attachments
     */
    public function create(
        School $school,
        string $name,
        string $classId,
        ?string $teacherId,
        ?string $contentHtml,
        array $attachments,
    ): Course {
        $class = $this->referenceResolver->resolveClassInSchool($school, $classId);

        $teacher = null;
        if (is_string($teacherId) && $teacherId !== '') {
            $teacher = $this->referenceResolver->resolveTeacherInSchool($school, $teacherId);
            if (!$teacher->getClasses()->contains($class)) {
                throw SchoolRelationException::unprocessable('teacherId is not assigned to classId');
            }
        }

        $course = (new Course())
            ->setSchoolClass($class)
            ->setTeacher($teacher)
            ->setName($name)
            ->setContentHtml($contentHtml)
            ->setAttachments($this->normalizeAttachments($attachments));

        $this->entityManager->persist($course);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new EntityCreated('school_course', $course->getId(), context: [
            'applicationSlug' => $class->getSchool()?->getApplication()?->getSlug(),
        ]));

        return $course;
    }

    /**
     * @param list<array{url: string, originalName: string, mimeType: string, size: int, extension: string}> $attachments
     * @return list<array<string,mixed>>
     */
    private function normalizeAttachments(array $attachments): array
    {
        $uploadedAt = new DateTimeImmutable();

        $normalized = [];
        foreach ($attachments as $attachment) {
            $normalized[] = [
                'url' => $attachment['url'],
                'originalName' => $attachment['originalName'],
                'mimeType' => $attachment['mimeType'],
                'size' => $attachment['size'],
                'extension' => $attachment['extension'],
                'uploadedAt' => $uploadedAt->format(DATE_ATOM),
            ];
        }

        return $normalized;
    }
}
