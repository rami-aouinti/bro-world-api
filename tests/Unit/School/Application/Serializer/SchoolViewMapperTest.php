<?php

declare(strict_types=1);

namespace App\Tests\Unit\School\Application\Serializer;

use App\School\Application\Serializer\SchoolViewMapper;
use App\School\Domain\Entity\Course;
use App\School\Domain\Entity\Exam;
use App\School\Domain\Entity\SchoolClass;
use App\School\Domain\Entity\Teacher;
use App\School\Domain\Enum\ExamStatus;
use App\School\Domain\Enum\ExamType;
use App\School\Domain\Enum\Term;
use PHPUnit\Framework\TestCase;

final class SchoolViewMapperTest extends TestCase
{
    public function testMapExamCollectionIncludesEnumValues(): void
    {
        $schoolClass = (new SchoolClass())->setName('Classe A - Sciences');
        $teacher = (new Teacher())->setName('Mme Martin');
        $exam = (new Exam())
            ->setTitle('Examen Mathematiques - Trimestre 1')
            ->setSchoolClass($schoolClass)
            ->setTeacher($teacher)
            ->setType(ExamType::FINAL)
            ->setStatus(ExamStatus::PUBLISHED)
            ->setTerm(Term::TERM_2);

        $result = (new SchoolViewMapper())->mapExamCollection([$exam]);

        self::assertSame('FINAL', $result[0]['type']);
        self::assertSame('PUBLISHED', $result[0]['status']);
        self::assertSame('TERM_2', $result[0]['term']);
    }

    public function testMapCourseIncludesHtmlContentAndAttachments(): void
    {
        $schoolClass = (new SchoolClass())->setName('Classe B - Informatique');
        $teacher = (new Teacher())->setName('M. Dupont');
        $course = (new Course())
            ->setName('Algorithmique avancée')
            ->setSchoolClass($schoolClass)
            ->setTeacher($teacher)
            ->setContentHtml('<h2>Chapitre 1</h2><p>Introduction</p>')
            ->setAttachments([
                [
                    'url' => '/uploads/school/courses/intro.pdf',
                    'originalName' => 'intro.pdf',
                    'mimeType' => 'application/pdf',
                    'size' => 12345,
                    'extension' => 'pdf',
                    'uploadedAt' => '2026-01-15T08:15:00+00:00',
                ],
            ]);

        $result = (new SchoolViewMapper())->mapCourse($course);

        self::assertSame('<h2>Chapitre 1</h2><p>Introduction</p>', $result['contentHtml']);
        self::assertCount(1, $result['attachments']);
        self::assertSame('/uploads/school/courses/intro.pdf', $result['attachments'][0]['url']);
    }
}
