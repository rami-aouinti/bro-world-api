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
        ],
    ];

    #[Override]
    public function load(ObjectManager $manager): void
    {
        foreach ($this->getApplicationsByPlatform(PlatformKey::SCHOOL) as $application) {
            $school = $manager->getRepository(School::class)->findOneBy(['application' => $application]);

            if (!$school instanceof School) {
                $school = (new School())
                    ->setApplication($application);
                $manager->persist($school);
            }

            $school->setName($application->getTitle() . ' Academy');

            $classA = (new SchoolClass())->setSchool($school)->setName('Classe A - Sciences');
            $classB = (new SchoolClass())->setSchool($school)->setName('Classe B - Langues');
            $manager->persist($classA);
            $manager->persist($classB);

            $teacherMath = (new Teacher())->setName('Mme Martin');
            $teacherFrench = (new Teacher())->setName('M. Dubois');
            $teacherMath->getClasses()->add($classA);
            $teacherFrench->getClasses()->add($classA);
            $teacherFrench->getClasses()->add($classB);
            $manager->persist($teacherMath);
            $manager->persist($teacherFrench);

            $students = [
                (new Student())->setSchoolClass($classA)->setName('Alice Bernard'),
                (new Student())->setSchoolClass($classA)->setName('Lucas Petit'),
                (new Student())->setSchoolClass($classB)->setName('Emma Laurent'),
            ];

            foreach ($students as $student) {
                $manager->persist($student);
            }

            $examMath = (new Exam())
                ->setSchoolClass($classA)
                ->setTeacher($teacherMath)
                ->setTitle('Examen Mathematiques - Trimestre 1')
                ->setType(ExamType::MIDTERM)
                ->setStatus(ExamStatus::PUBLISHED)
                ->setTerm(Term::TERM_1);
            $examFrench = (new Exam())
                ->setSchoolClass($classB)
                ->setTeacher($teacherFrench)
                ->setTitle('Examen Francais - Trimestre 1')
                ->setType(ExamType::QUIZ)
                ->setStatus(ExamStatus::DRAFT)
                ->setTerm(Term::TERM_1);
            $manager->persist($examMath);
            $manager->persist($examFrench);

            $manager->persist((new Grade())->setStudent($students[0])->setExam($examMath)->setScore(16.5));
            $manager->persist((new Grade())->setStudent($students[1])->setExam($examMath)->setScore(14.0));
            $manager->persist((new Grade())->setStudent($students[2])->setExam($examFrench)->setScore(17.0));
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
