<?php

declare(strict_types=1);

namespace App\Recruit\Infrastructure\DataFixtures\ORM;

use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Certification;
use App\Recruit\Domain\Entity\Education;
use App\Recruit\Domain\Entity\Experience;
use App\Recruit\Domain\Entity\Hobby;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Language;
use App\Recruit\Domain\Entity\Project;
use App\Recruit\Domain\Entity\Reference as ResumeReference;
use App\Recruit\Domain\Entity\Resume;
use App\Recruit\Domain\Entity\Skill;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\User\Domain\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadRecruitApplicationData extends Fixture implements OrderedFixtureInterface
{
    #[Override]
    public function load(ObjectManager $manager): void
    {
        $jobIncomingForRoot = $this->getReference('Recruit-Job-025', Job::class);
        $jobOwnedByOtherUser = $this->getReference('Recruit-Job-001', Job::class);

        $johnRootApplicant = $this->createApplicantWithResume(
            owner: $this->getReference('User-john-root', User::class),
            key: 'john-root',
            coverLetter: 'Je candidate avec une forte expérience fullstack et produit.'
        );

        $johnAdminApplicant = $this->createApplicantWithResume(
            owner: $this->getReference('User-john-admin', User::class),
            key: 'john-admin',
            coverLetter: 'Profil orienté architecture backend et sécurité applicative.'
        );

        $johnUserApplicant = $this->createApplicantWithResume(
            owner: $this->getReference('User-john-user', User::class),
            key: 'john-user',
            coverLetter: 'Développeur frontend confirmé avec expérience design system.'
        );

        $johnApiApplicant = $this->createApplicantWithResume(
            owner: $this->getReference('User-john-api', User::class),
            key: 'john-api',
            coverLetter: 'Ingénieur API orienté performance, qualité et DX.'
        );

        $johnLoggedApplicant = $this->createApplicantWithResume(
            owner: $this->getReference('User-john-logged', User::class),
            key: 'john-logged',
            coverLetter: 'Profil polyvalent pour postes techniques orientés SaaS B2B.'
        );

        foreach ([$johnRootApplicant, $johnAdminApplicant, $johnUserApplicant, $johnApiApplicant, $johnLoggedApplicant] as $entity) {
            $manager->persist($entity->getResume());
            $manager->persist($entity);
        }

        $this->createApplication(
            applicant: $johnAdminApplicant,
            job: $jobIncomingForRoot,
            reference: 'Recruit-Application-john-admin-incoming-root-waiting'
        );

        $this->createApplication(
            applicant: $johnUserApplicant,
            job: $jobIncomingForRoot,
            reference: 'Recruit-Application-john-user-incoming-root-reviewing',
            status: ApplicationStatus::REVIEWING
        );

        $this->createApplication(
            applicant: $johnApiApplicant,
            job: $jobIncomingForRoot,
            reference: 'Recruit-Application-john-api-incoming-root-interview',
            status: ApplicationStatus::INTERVIEW
        );

        $this->createApplication(
            applicant: $johnLoggedApplicant,
            job: $jobIncomingForRoot,
            reference: 'Recruit-Application-john-logged-incoming-root-rejected',
            status: ApplicationStatus::REJECTED
        );

        $this->createApplication(
            applicant: $johnRootApplicant,
            job: $jobOwnedByOtherUser,
            reference: 'Recruit-Application-john-root-on-other-owner-waiting'
        );

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 9;
    }

    private function createApplicantWithResume(User $owner, string $key, string $coverLetter): Applicant
    {
        $resume = (new Resume())->setOwner($owner);
        $resume
            ->addExperience((new Experience())->setTitle('Senior Developer')->setDescription('8+ ans en développement web et API.'))
            ->addExperience((new Experience())->setTitle('Lead Projet')->setDescription('Pilotage technique, mentoring et revue de code.'))
            ->addEducation((new Education())->setTitle('Master Informatique')->setDescription('Spécialisation architecture logicielle.'))
            ->addEducation((new Education())->setTitle('Certification Agile')->setDescription('Pratiques Scrum et delivery continue.'))
            ->addSkill((new Skill())->setTitle('PHP / Symfony')->setDescription('Conception DDD, CQRS et APIs robustes.'))
            ->addSkill((new Skill())->setTitle('TypeScript / React')->setDescription('UI complexes, tests front et accessibilité.'))
            ->addLanguage((new Language())->setTitle('Français')->setDescription('Natif'))
            ->addLanguage((new Language())->setTitle('Anglais')->setDescription('Professionnel'))
            ->addCertification((new Certification())->setTitle('AWS Cloud Practitioner')->setDescription('Fondamentaux cloud et sécurité.'))
            ->addCertification((new Certification())->setTitle('Doctrine ORM Expert')->setDescription('Optimisation des mappings et requêtes.'))
            ->addProject((new Project())->setTitle('Plateforme RH')->setDescription('Mise en place d\'un ATS multi-tenant.'))
            ->addProject((new Project())->setTitle('Suite API interne')->setDescription('Refonte et documentation OpenAPI.'))
            ->addReference((new ResumeReference())->setTitle('CTO précédent')->setDescription('Référence managériale et technique.'))
            ->addReference((new ResumeReference())->setTitle('Product Owner')->setDescription('Référence orientée collaboration produit.'))
            ->addHobby((new Hobby())->setTitle('Open source')->setDescription('Contributions régulières sur des libs PHP.'))
            ->addHobby((new Hobby())->setTitle('Course à pied')->setDescription('Discipline et constance en entraînement.'));

        $applicant = (new Applicant())
            ->setUser($owner)
            ->setResume($resume)
            ->setCoverLetter($coverLetter);

        $this->addReference('Recruit-Resume-' . $key, $resume);
        $this->addReference('Recruit-Applicant-' . $key, $applicant);

        return $applicant;
    }

    private function createApplication(
        Applicant $applicant,
        Job $job,
        string $reference,
        ApplicationStatus $status = ApplicationStatus::WAITING,
    ): void {
        $application = (new Application())
            ->setApplicant($applicant)
            ->setJob($job)
            ->setStatus($status);

        $applicant->addApplication($application);
        $this->addReference($reference, $application);
    }
}
