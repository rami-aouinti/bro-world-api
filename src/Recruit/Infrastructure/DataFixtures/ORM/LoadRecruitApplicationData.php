<?php

declare(strict_types=1);

namespace App\Recruit\Infrastructure\DataFixtures\ORM;

use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\ApplicationStatusHistory;
use App\Recruit\Domain\Entity\Certification;
use App\Recruit\Domain\Entity\Education;
use App\Recruit\Domain\Entity\Experience;
use App\Recruit\Domain\Entity\Hobby;
use App\Recruit\Domain\Entity\Interview;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Language;
use App\Recruit\Domain\Entity\Project;
use App\Recruit\Domain\Entity\Reference as ResumeReference;
use App\Recruit\Domain\Entity\Resume;
use App\Recruit\Domain\Entity\Skill;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Recruit\Domain\Enum\InterviewMode;
use App\Recruit\Domain\Enum\InterviewStatus;
use App\User\Domain\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadRecruitApplicationData extends Fixture implements OrderedFixtureInterface
{
    private const int HIGH_VOLUME_APPLICANTS_COUNT = 40;

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

        $aliceApplicant = $this->createApplicantWithResume(
            owner: $this->getReference('User-alice', User::class),
            key: 'alice',
            coverLetter: 'Candidate orientée coordination d\'entretiens et expérience candidat.'
        );

        foreach ([$johnRootApplicant, $johnAdminApplicant, $johnUserApplicant, $johnApiApplicant, $johnLoggedApplicant, $aliceApplicant] as $entity) {
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
            reference: 'Recruit-Application-john-user-incoming-root-screening',
            status: ApplicationStatus::SCREENING
        );

        $this->createApplication(
            applicant: $johnApiApplicant,
            job: $jobIncomingForRoot,
            reference: 'Recruit-Application-john-api-incoming-root-interview-done',
            status: ApplicationStatus::INTERVIEW_DONE
        );

        $this->createApplication(
            applicant: $johnLoggedApplicant,
            job: $jobIncomingForRoot,
            reference: 'Recruit-Application-john-logged-incoming-root-rejected',
            status: ApplicationStatus::REJECTED
        );

        $this->createApplication(
            applicant: $johnAdminApplicant,
            job: $jobOwnedByOtherUser,
            reference: 'Recruit-Application-john-admin-on-other-owner-screening',
            status: ApplicationStatus::SCREENING
        );

        $this->createApplication(
            applicant: $johnUserApplicant,
            job: $jobOwnedByOtherUser,
            reference: 'Recruit-Application-john-user-on-other-owner-interview-planned-done',
            status: ApplicationStatus::INTERVIEW_PLANNED
        );

        $this->createApplication(
            applicant: $johnApiApplicant,
            job: $jobOwnedByOtherUser,
            reference: 'Recruit-Application-john-api-on-other-owner-hired',
            status: ApplicationStatus::HIRED
        );

        $offerSentApplication = $this->createApplication(
            applicant: $johnLoggedApplicant,
            job: $jobOwnedByOtherUser,
            reference: 'Recruit-Application-john-logged-on-other-owner-offer-sent',
            status: ApplicationStatus::OFFER_SENT
        );

        $noShowApplication = $this->createApplication(
            applicant: $aliceApplicant,
            job: $jobOwnedByOtherUser,
            reference: 'Recruit-Application-alice-on-other-owner-no-show',
            status: ApplicationStatus::INTERVIEW_PLANNED
        );

        $this->createApplication(
            applicant: $johnRootApplicant,
            job: $jobOwnedByOtherUser,
            reference: 'Recruit-Application-john-root-on-other-owner-waiting'
        );

        $manager->persist($this->createInterview(
            application: $this->getReference('Recruit-Application-john-user-on-other-owner-interview-planned-done', Application::class),
            scheduleModifier: '+2 day 10:00',
            interviewerIds: ['john-root', 'john-admin'],
            mode: InterviewMode::VISIO,
            status: InterviewStatus::PLANNED,
            notes: 'Panel technique planifié. Préparer un exercice API.'
        ));

        $manager->persist($this->createInterview(
            application: $this->getReference('Recruit-Application-john-api-incoming-root-interview-done', Application::class),
            scheduleModifier: '-1 day 14:00',
            interviewerIds: ['john-root'],
            mode: InterviewMode::ON_SITE,
            status: InterviewStatus::DONE,
            notes: 'Très bon feedback technique, communication claire.'
        ));

        $manager->persist($this->createInterview(
            application: $noShowApplication,
            scheduleModifier: '+1 day 09:30',
            interviewerIds: ['john-root', 'john-admin', 'john-root'],
            mode: InterviewMode::VISIO,
            status: InterviewStatus::CANCELED,
            notes: 'No-show candidat : absence sans prévenir 15 min après l\'horaire.'
        ));

        $manager->persist($this->createStatusHistory(
            application: $this->getReference('Recruit-Application-john-user-incoming-root-screening', Application::class),
            author: $this->getReference('User-john-root', User::class),
            fromStatus: ApplicationStatus::WAITING,
            toStatus: ApplicationStatus::SCREENING,
            comment: 'Feedback CV: stack alignée, manque un peu d\'expérience lead.'
        ));

        $manager->persist($this->createStatusHistory(
            application: $offerSentApplication,
            author: $this->getReference('User-john-root', User::class),
            fromStatus: ApplicationStatus::INTERVIEW_DONE,
            toStatus: ApplicationStatus::OFFER_SENT,
            comment: 'Offre envoyée: 68k EUR + variable, démarrage sous 1 mois.'
        ));

        $manager->persist($this->createStatusHistory(
            application: $noShowApplication,
            author: $this->getReference('User-john-admin', User::class),
            fromStatus: ApplicationStatus::SCREENING,
            toStatus: ApplicationStatus::INTERVIEW_PLANNED,
            comment: 'Relance envoyée après no-show, en attente de replanification.'
        ));

        $this->createHighVolumeApplications(
            manager: $manager,
            ownerPrefix: 'stress-volume',
            coverLetterPrefix: 'Candidature volume',
            job: $jobOwnedByOtherUser,
            status: ApplicationStatus::SCREENING,
            count: self::HIGH_VOLUME_APPLICANTS_COUNT,
            owner: $this->getReference('User-john-api', User::class)
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

        if ($key === 'john-root') {
            $resume
                ->setInformationFullName('Rami Aouinti')
                ->setInformationEmail('rami.aouinti@gmail.com')
                ->setInformationPhone('0049 176/35587613')
                ->setInformationHomepage('https://www.ramy-aouinti.com')
                ->setInformationRepoProfile('https://github.com/rami-aouinti')
                ->setInformationAddress('50589 Köln Germany')
                ->setInformationBirthDate(new \DateTimeImmutable('1989-08-25'))
                ->setInformationBirthPlace('Tunesien')
                ->setInformationProfileText("Softwareentwickler mit Schwerpunkt PHP/Web und Symfony, spezialisiert auf die Entwicklung robuster, skalierbarer und wartbarer Webanwendungen.\nIch arbeite hauptsächlich an der Erstellung von APIs, Backend-Architekturen, Service-Integrationen und Performance-Optimierung.\nStrukturiert, selbstständig und qualitätsorientiert entwickle ich zuverlässige Lösungen, die auf fachliche Anforderungen zugeschnitten sind.")
                ->setInformationTitle('Software Entwickler')
                ->setInformationPhoto('https://bro-world.org/img/team-1.jpg');
        } else {
            $resume
                ->setInformationFullName($owner->getFirstName() . ' ' . $owner->getLastName())
                ->setInformationEmail($owner->getEmail())
                ->setInformationPhone($owner->getProfile()?->getPhone())
                ->setInformationAddress($owner->getProfile()?->getLocation());
        }

        $resume
            ->addExperience((new Experience())->setTitle('Senior Developer')->setDescription('8+ ans en développement web et API.')->setCompany('Bro World')->setStartDate(new \DateTimeImmutable('2018-01-01'))->setEndDate(new \DateTimeImmutable('2021-12-31')))
            ->addExperience((new Experience())->setTitle('Lead Projet')->setDescription('Pilotage technique, mentoring et revue de code.')->setCompany('Bro World Labs')->setStartDate(new \DateTimeImmutable('2022-01-01')))
            ->addEducation((new Education())->setTitle('Master Informatique')->setDescription('Spécialisation architecture logicielle.')->setSchool('Université de Paris')->setStartDate(new \DateTimeImmutable('2014-09-01'))->setEndDate(new \DateTimeImmutable('2016-06-30'))->setLocation('Paris'))
            ->addEducation((new Education())->setTitle('Certification Agile')->setDescription('Pratiques Scrum et delivery continue.')->setSchool('Scrum Institute')->setStartDate(new \DateTimeImmutable('2017-01-01'))->setEndDate(new \DateTimeImmutable('2017-02-01'))->setLocation('Lyon'))
            ->addSkill((new Skill())->setTitle('PHP / Symfony')->setDescription('Conception DDD, CQRS et APIs robustes.'))
            ->addSkill((new Skill())->setTitle('TypeScript / React')->setDescription('UI complexes, tests front et accessibilité.'))
            ->addLanguage((new Language())->setTitle('Français')->setDescription('FR')->setLevel('90'))
            ->addLanguage((new Language())->setTitle('Anglais')->setDescription('US')->setLevel('80'))
            ->addCertification((new Certification())->setTitle('AWS Cloud Practitioner')->setDescription('Fondamentaux cloud et sécurité.')->setAttachments(['https://cdn.example.com/certs/aws-cloud.pdf']))
            ->addCertification((new Certification())->setTitle('Doctrine ORM Expert')->setDescription('Optimisation des mappings et requêtes.')->setAttachments(['https://cdn.example.com/certs/doctrine-orm.pdf']))
            ->addProject((new Project())->setTitle('Plateforme RH')->setDescription('Mise en place d\'un ATS multi-tenant.')->setHomePage('https://rh.example.com')->setAttachments(['https://cdn.example.com/projects/rh-spec.pdf']))
            ->addProject((new Project())->setTitle('Suite API interne')->setDescription('Refonte et documentation OpenAPI.')->setHomePage('https://api.example.com')->setAttachments(['https://cdn.example.com/projects/api-docs.pdf']))
            ->addReference((new ResumeReference())->setTitle('CTO précédent')->setDescription('Référence managériale et technique.'))
            ->addReference((new ResumeReference())->setTitle('Product Owner')->setDescription('Référence orientée collaboration produit.'))
            ->addHobby((new Hobby())->setTitle('Open source')->setDescription('Contributions régulières sur des libs PHP.'))
            ->addHobby((new Hobby())->setTitle('Course à pied')->setDescription('Discipline et constance en entraînement.'));

        if ($key === 'john-root') {
            $resume
                ->addEducation((new Education())->setTitle('Master Telekommunikation und Informationstechnik')->setDescription('Beuth Hoch-schule für Technik, Berlin')->setSchool('Beuth Hoch-schule für Technik, Berlin')->setStartDate(new \DateTimeImmutable('2016-10-01'))->setEndDate(new \DateTimeImmutable('2018-08-31'))->setLocation('Berlin'))
                ->addEducation((new Education())->setTitle('Ingenieur Computernetzwerke und Telekommunikation')->setDescription('Universität INSAT, Tunis')->setSchool('Universität INSAT, Tunis')->setStartDate(new \DateTimeImmutable('2008-09-01'))->setEndDate(new \DateTimeImmutable('2013-01-31'))->setLocation('Tunis'))
                ->addExperience((new Experience())->setTitle('Backend Developer')->setDescription('Entwicklung anspruchsvoller Backend-Lösungen mit Symfony 6, RESTful APIs und Microservices.')->setCompany('TKDeutschland GmbH')->setStartDate(new \DateTimeImmutable('2024-02-01')))
                ->addExperience((new Experience())->setTitle('Senior Full Stack Entwickler E-Commerce und Web-Applikationen')->setDescription('Analyse und technische Umsetzung funktionaler Anforderungen, API-Integrationen und Performance-Optimierung.')->setCompany('Hinke GmbH')->setStartDate(new \DateTimeImmutable('2021-11-01'))->setEndDate(new \DateTimeImmutable('2024-01-31')))
                ->addExperience((new Experience())->setTitle('Junior Full Stack Web Entwickler E-Commerce')->setDescription('Entwicklung komplexer E-Commerce-Lösungen, RESTful APIs und Frontend-Funktionen mit Vue.js.')->setCompany('Wizmo GmbH')->setStartDate(new \DateTimeImmutable('2018-08-01'))->setEndDate(new \DateTimeImmutable('2021-04-30')))
                ->addLanguage((new Language())->setTitle('Deutsch')->setDescription('DE')->setLevel('90'))
                ->addLanguage((new Language())->setTitle('English')->setDescription('US')->setLevel('80'))
                ->addLanguage((new Language())->setTitle('Français')->setDescription('FR')->setLevel('90'))
                ->addSkill((new Skill())->setTitle('Advanced Communication Skills')->setDescription('Communication')->setLevel('100'))
                ->addSkill((new Skill())->setTitle('Office Technology Skills')->setDescription('Tools')->setLevel('100'))
                ->addSkill((new Skill())->setTitle('Motivated Attitude')->setDescription('Soft Skill')->setLevel('100'))
                ->addSkill((new Skill())->setTitle('Social Media Platforms')->setDescription('Platform management')->setLevel('100'))
                ->addSkill((new Skill())->setTitle('Symfony 6')->setDescription('Framework')->setLevel('5/5'))
                ->addSkill((new Skill())->setTitle('Laravel 6 & 8')->setDescription('Framework')->setLevel('4/5'))
                ->addSkill((new Skill())->setTitle('ZendFramework 2')->setDescription('Framework')->setLevel('4/5'))
                ->addSkill((new Skill())->setTitle('Oxid eSales Shop 6, Shopware 5.6 & 6, Drupal, Typo3, WordPress')->setDescription('CMS')->setLevel('4/5'))
                ->addSkill((new Skill())->setTitle('SQL, DQL, MongoDB, Redis')->setDescription('Database')->setLevel('5/5'))
                ->addProject((new Project())->setTitle('Bro World Space')->setDescription('Community and collaboration platform project.')->setHomePage('https://github.com/rami-aouinti/bro-world-api')->setAttachments(['https://bro-world.org/img/social-bro-world.png']));
        }

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
    ): Application {
        $application = (new Application())
            ->setApplicant($applicant)
            ->setJob($job)
            ->setStatus($status);

        $applicant->addApplication($application);
        $this->addReference($reference, $application);

        return $application;
    }

    private function createInterview(
        Application $application,
        string $scheduleModifier,
        array $interviewerIds,
        InterviewMode $mode,
        InterviewStatus $status,
        ?string $notes = null,
    ): Interview {
        return (new Interview())
            ->setApplication($application)
            ->setScheduledAt((new \DateTimeImmutable($scheduleModifier)))
            ->setDurationMinutes(60)
            ->setMode($mode)
            ->setLocationOrUrl($mode === InterviewMode::VISIO ? 'https://meet.local/recruit-interview' : 'HQ Stuttgart')
            ->setInterviewerIds($interviewerIds)
            ->setStatus($status)
            ->setNotes($notes);
    }

    private function createStatusHistory(
        Application $application,
        User $author,
        ApplicationStatus $fromStatus,
        ApplicationStatus $toStatus,
        ?string $comment,
    ): ApplicationStatusHistory {
        return (new ApplicationStatusHistory())
            ->setApplication($application)
            ->setAuthor($author)
            ->setFromStatus($fromStatus)
            ->setToStatus($toStatus)
            ->setComment($comment);
    }

    private function createHighVolumeApplications(
        ObjectManager $manager,
        string $ownerPrefix,
        string $coverLetterPrefix,
        Job $job,
        ApplicationStatus $status,
        int $count,
        User $owner,
    ): void {
        for ($i = 1; $i <= $count; $i++) {
            $key = $ownerPrefix . '-' . $i;
            $applicant = $this->createApplicantWithResume(
                owner: $owner,
                key: $key,
                coverLetter: $coverLetterPrefix . ' #' . $i
            );

            $manager->persist($applicant->getResume());
            $manager->persist($applicant);

            $this->createApplication(
                applicant: $applicant,
                job: $job,
                reference: 'Recruit-Application-' . $key,
                status: $status
            );
        }
    }
}
