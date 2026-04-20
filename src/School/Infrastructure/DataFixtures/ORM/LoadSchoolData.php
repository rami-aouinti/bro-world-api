<?php

declare(strict_types=1);

namespace App\School\Infrastructure\DataFixtures\ORM;

use App\General\Domain\Enum\Language;
use App\General\Domain\Enum\Locale;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use App\School\Domain\Entity\Course;
use App\School\Domain\Entity\Exam;
use App\School\Domain\Entity\Grade;
use App\School\Domain\Entity\LearningSessionNote;
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

        $userPool = [
            $this->getReference('User-alice', User::class),
            $this->getReference('User-bruno', User::class),
            $this->getReference('User-clara', User::class),
            $this->getReference('User-bob', User::class),
            $this->getReference('User-charlie', User::class),
            $this->getReference('User-diana', User::class),
            $this->getReference('User-emma', User::class),
            $this->getReference('User-felix', User::class),
            $this->getReference('User-grace', User::class),
            $this->getReference('User-john-user', User::class),
            $this->getReference('User-john-logged', User::class),
            $this->getReference('User-john-api', User::class),
            $this->getReference('User-john-admin', User::class),
            $this->getReference('User-john-root', User::class),
        ];
        $userCursor = 0;

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
                'small' => 'Informatique - Grad 1',
                'medium' => 'Informatique - Grad 2',
                'large' => 'Informatique - Grad 3',
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

            $teacherMath = (new Teacher())->setUser($this->createSchoolUser($manager, $appKey . '-teacher-math', 'Teacher', 'Math'));
            $teacherFrench = (new Teacher())->setUser($this->createSchoolUser($manager, $appKey . '-teacher-french', 'Teacher', 'French'));
            $teacherHead = (new Teacher())->setUser($this->createSchoolUser($manager, $appKey . '-teacher-head', 'Teacher', 'Head'));

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

            $coursesByClass = [];
            foreach ($classes as $classLabel => $class) {
                foreach (['Algorithmique', 'Base de Données', 'Réseaux'] as $index => $courseLabel) {
                    $isGeneralSchool = $appKey === 'school-general-core';
                    $normalizedClassLabel = $classLabel;
                    $normalizedCourseLabel = strtolower(str_replace(' ', '-', $courseLabel));

                    $course = (new Course())
                        ->setSchoolClass($class)
                        ->setTeacher($index === 1 ? $teacherFrench : $teacherMath)
                        ->setName($courseLabel . ' - ' . $classLabel . ' - ' . $applicationIndex)
                        ->setContentHtml($isGeneralSchool ? '<h2>' . $courseLabel . '</h2><p>Programme détaillé pour la classe ' . $class->getName() . '.</p><ul><li>Objectifs</li><li>Exercices</li><li>Évaluation continue</li></ul>' : null)
                        ->setAttachments($isGeneralSchool ? [
                            [
                                'url' => '/uploads/school/fixtures/' . $appKey . '/' . $normalizedClassLabel . '/' . $normalizedCourseLabel . '-plan.pdf',
                                'originalName' => $courseLabel . '-plan.pdf',
                                'mimeType' => 'application/pdf',
                                'size' => 124500 + ($index * 1042),
                                'extension' => 'pdf',
                                'uploadedAt' => '2026-01-15T08:15:00+00:00',
                            ],
                            [
                                'url' => '/uploads/school/fixtures/' . $appKey . '/' . $normalizedClassLabel . '/' . $normalizedCourseLabel . '-slides.pptx',
                                'originalName' => $courseLabel . '-slides.pptx',
                                'mimeType' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                'size' => 280900 + ($index * 2048),
                                'extension' => 'pptx',
                                'uploadedAt' => '2026-01-15T08:20:00+00:00',
                            ],
                        ] : []);
                    $manager->persist($course);
                    $coursesByClass[$classLabel][] = $course;
                }
            }

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
                    $studentUser = $this->createSchoolUser(
                        $manager,
                        $appKey . '-student-' . $classLabel . '-' . $i,
                        'Student',
                        ucfirst($classLabel) . (string)$i,
                    );

                    $student = (new Student())
                        ->setSchoolClass($classes[$classLabel])
                        ->setUser($studentUser);
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
                    $course = $coursesByClass[$classLabel][$i % count($coursesByClass[$classLabel])];
                    $exam = (new Exam())
                        ->setSchoolClass($class)
                        ->setCourse($course)
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
                        $score = (float)(($studentIndex + $examIndex + $applicationIndex) % 21);

                        $grade = (new Grade())
                            ->setStudent($student)
                            ->setExam($exam)
                            ->setCourse($exam->getCourse())
                            ->setScore($score);
                        $manager->persist($grade);
                        $this->addReference('Grade-' . $appKey . '-' . $gradeCounter, $grade);

                        $note = (new LearningSessionNote())
                            ->setStudent($student)
                            ->setExam($exam)
                            ->setCourse($exam->getCourse())
                            ->setScore($score)
                            ->setPassed($score >= 10.0);
                        $manager->persist($note);

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

    private function createSchoolUser(ObjectManager $manager, string $key, string $firstName, string $lastName): User
    {
        $username = 'school-' . strtolower($key);

        $user = (new User())
            ->setUsername($username)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setEmail($username . '@test.com')
            ->setLanguage(Language::EN)
            ->setLocale(Locale::EN)
            ->setPlainPassword('password-' . $username);

        $manager->persist($user);

        return $user;
    }
}
