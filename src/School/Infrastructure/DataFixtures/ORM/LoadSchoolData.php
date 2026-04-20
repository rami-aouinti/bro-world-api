<?php

declare(strict_types=1);

namespace App\School\Infrastructure\DataFixtures\ORM;

use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use App\School\Domain\Entity\Exam;
use App\School\Domain\Entity\Grade;
use App\School\Domain\Entity\School;
use App\School\Domain\Entity\SchoolClass;
use App\School\Domain\Entity\Student;
use App\School\Domain\Entity\Teacher;
use App\School\Domain\Enum\ExamStatus;
use App\School\Domain\Enum\ExamType;
use App\School\Domain\Enum\Term;
use App\User\Domain\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadSchoolData extends Fixture implements OrderedFixtureInterface
{
    /**
     * @var array<non-empty-string, array<int, non-empty-string>>
     */
    private const array APPLICATION_KEYS_BY_PLATFORM = [
        PlatformKey::SCHOOL->value => [
            'school-campus-core',
            'school-course-flow',
            'school-grade-track',
            'school-general-core',
        ],
    ];

    #[Override]
    public function load(ObjectManager $manager): void
    {
        $generalOwner = $this->getReference('User-john-root', User::class);
        $examTypes = ExamType::cases();
        $examStatuses = ExamStatus::cases();
        $terms = Term::cases();
        $applicationKeys = self::APPLICATION_KEYS_BY_PLATFORM[PlatformKey::SCHOOL->value] ?? [];

        foreach ($this->getApplicationsByPlatform(PlatformKey::SCHOOL) as $applicationIndex => $application) {
            $appKey = $applicationKeys[$applicationIndex] ?? $application->getSlug();

            $school = $manager->getRepository(School::class)->findOneBy([
                'application' => $application,
            ]);

            if (!$school instanceof School) {
                $school = (new School())
                    ->setApplication($application);
                $manager->persist($school);
            }

            $school->setName($application->getTitle() . ' Academy');
            $this->addReference('School-' . $appKey, $school);
            if ($appKey === 'school-general-core') {
                $this->addReference('School-General-Core', $school);
                $this->addReference('School-general', $school);
            }

            $classes = [];
            $classesByLabel = [
                'small' => 'Classe A - Sciences',
                'medium' => 'Classe B - Langues',
                'large' => 'Classe C - Technologies',
            ];

            foreach ($classesByLabel as $label => $name) {
                $class = (new SchoolClass())
                    ->setSchool($school)
                    ->setName($name);
                $manager->persist($class);

                $classes[$label] = $class;
                $this->addReference('SchoolClass-' . $appKey . '-' . $label, $class);
                if ($appKey === 'school-general-core') {
                    $this->addReference('SchoolClass-general-' . $label, $class);
                }
            }
            $this->addReference('SchoolClass-' . $appKey . '-1', $classes['small']);

            $teacherMath = (new Teacher())->setName('Mme Martin - ' . $applicationIndex);
            $teacherFrench = (new Teacher())->setName('M. Dubois - ' . $applicationIndex);
            $teacherHead = (new Teacher())->setName('Dr. Principal - ' . $applicationIndex);

            $teacherMath->getClasses()->add($classes['small']);
            $teacherMath->getClasses()->add($classes['large']);
            $teacherFrench->getClasses()->add($classes['small']);
            $teacherFrench->getClasses()->add($classes['medium']);
            $teacherHead->getClasses()->add($classes['small']);
            $teacherHead->getClasses()->add($classes['medium']);
            $teacherHead->getClasses()->add($classes['large']);

            $manager->persist($teacherMath);
            $manager->persist($teacherFrench);
            $manager->persist($teacherHead);

            $this->addReference('Teacher-' . $appKey . '-math', $teacherMath);
            $this->addReference('Teacher-' . $appKey . '-french', $teacherFrench);
            $this->addReference('Teacher-' . $appKey . '-head', $teacherHead);
            $this->addReference('Teacher-' . $appKey . '-1', $teacherMath);

            $students = [];
            $studentCounter = 1;
            foreach (
                [
                    'small' => 3,
                    'medium' => 8,
                    'large' => 15,
                ] as $classLabel => $count
            ) {
                for ($i = 1; $i <= $count; $i++) {
                    $student = (new Student())
                        ->setSchoolClass($classes[$classLabel])
                        ->setName('Student ' . $applicationIndex . '-' . $classLabel . '-' . $i);
                    $manager->persist($student);

                    $students[$classLabel][] = $student;
                    $this->addReference('Student-' . $appKey . '-' . $studentCounter, $student);
                    $studentCounter++;
                }
            }

            $exams = [];
            $examCounter = 1;
            foreach ($classes as $classLabel => $class) {
                for ($i = 0; $i < 4; $i++) {
                    $exam = (new Exam())
                        ->setSchoolClass($class)
                        ->setTeacher($i % 2 === 0 ? $teacherMath : $teacherHead)
                        ->setTitle('Examen ' . $classLabel . ' #' . ($i + 1) . ' - ' . $applicationIndex)
                        ->setType($examTypes[($applicationIndex + $i) % count($examTypes)])
                        ->setStatus($examStatuses[($applicationIndex + $i) % count($examStatuses)])
                        ->setTerm($terms[($applicationIndex + $i) % count($terms)]);
                    $manager->persist($exam);

                    $exams[$classLabel][] = $exam;
                    $this->addReference('Exam-' . $appKey . '-' . $examCounter, $exam);
                    $examCounter++;
                }
            }

            $gradeCounter = 1;
            foreach ($exams as $classLabel => $classExams) {
                foreach ($classExams as $examIndex => $exam) {
                    foreach ($students[$classLabel] as $studentIndex => $student) {
                        $score = 20.0;
                        if ($studentIndex === 0 && $examIndex === 0) {
                            $score = 0.0;
                        } elseif ($studentIndex === 1 && $examIndex === 1) {
                            $score = -1.0;
                        } elseif ($studentIndex === 2 && $examIndex === 2) {
                            $score = 25.0;
                        } elseif ($studentIndex === 3 && $examIndex === 3) {
                            $score = 9.999;
                        } else {
                            $score = (float)(($studentIndex + $examIndex + $applicationIndex) % 21);
                        }

                        $grade = (new Grade())
                            ->setStudent($student)
                            ->setExam($exam)
                            ->setScore($score);
                        $manager->persist($grade);
                        $this->addReference('Grade-' . $appKey . '-' . $gradeCounter, $grade);
                        $gradeCounter++;
                    }
                }
            }
        }

        if ($generalOwner instanceof User) {
            $this->addReference('School-General-Owner', $generalOwner);
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 11;
    }

    /**
     * @return array<int, Application>
     */
    private function getApplicationsByPlatform(PlatformKey $platformKey): array
    {
        $applications = [];

        foreach (self::APPLICATION_KEYS_BY_PLATFORM[$platformKey->value] ?? [] as $applicationKey) {
            $applications[] = $this->getReference('Application-' . $applicationKey, Application::class);
        }

        return $applications;
    }
}
