<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\DataFixtures\ORM;

use App\Crm\Domain\Entity\Company;
use App\Crm\Domain\Entity\Crm;
use App\Crm\Domain\Entity\Project;
use App\Crm\Domain\Entity\Sprint;
use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Enum\ProjectStatus;
use App\Crm\Domain\Enum\SprintStatus;
use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskRequestStatus;
use App\Crm\Domain\Enum\TaskStatus;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadCrmData extends Fixture implements OrderedFixtureInterface
{
    /**
     * @var array<non-empty-string, array<int, non-empty-string>>
     */
    private const array APPLICATION_KEYS_BY_PLATFORM = [
        PlatformKey::CRM->value => [
            'crm-sales-hub',
            'crm-pipeline-pro',
            'crm-support-desk',
        ],
    ];

    #[Override]
    public function load(ObjectManager $manager): void
    {
        foreach ($this->getApplicationsByPlatform(PlatformKey::CRM) as $application) {
            /** @var Crm|null $crm */
            $crm = $manager->getRepository(Crm::class)->findOneBy([
                'application' => $application,
            ]);

            if (!$crm instanceof Crm) {
                $crm = (new Crm())->setApplication($application);
                $manager->persist($crm);
            }

            $companies = [
                new Company()
                    ->setCrm($crm)
                    ->setName($application->getTitle() . ' - Acme Corp')
                    ->setIndustry('SaaS')
                    ->setWebsite('https://acme.example.com')
                    ->setContactEmail('contact@acme.example.com')
                    ->setPhone('+33 1 00 00 00 00'),
                new Company()
                    ->setCrm($crm)
                    ->setName($application->getTitle() . ' - Globex')
                    ->setIndustry('Consulting')
                    ->setWebsite('https://globex.example.com')
                    ->setContactEmail('sales@globex.example.com')
                    ->setPhone('+33 1 11 11 11 11'),
            ];

            foreach ($companies as $companyIndex => $company) {
                $manager->persist($company);

                $project = new Project()
                    ->setCompany($company)
                    ->setName($company->getName() . ' - Projet Transformation')
                    ->setCode('PRJ-' . (string)($companyIndex + 1))
                    ->setDescription('Optimisation du pipeline CRM et outillage commercial')
                    ->setStatus(ProjectStatus::ACTIVE)
                    ->setStartedAt(new DateTimeImmutable('-12 days'))
                    ->setDueAt(new DateTimeImmutable('+60 days'));
                $manager->persist($project);

                $sprint = new Sprint()
                    ->setProject($project)
                    ->setName('Sprint ' . (string)($companyIndex + 1))
                    ->setGoal('Livrer les automatisations de relance')
                    ->setStatus(SprintStatus::ACTIVE)
                    ->setStartDate(new DateTimeImmutable('-7 days'))
                    ->setEndDate(new DateTimeImmutable('+7 days'));
                $manager->persist($sprint);

                $taskBacklog = new Task()
                    ->setProject($project)
                    ->setSprint($sprint)
                    ->setTitle('Consolider le backlog')
                    ->setDescription('Rassembler toutes les opportunités dans un backlog unique')
                    ->setStatus(TaskStatus::IN_PROGRESS)
                    ->setPriority(TaskPriority::HIGH)
                    ->setDueAt(new DateTimeImmutable('+10 days'))
                    ->setEstimatedHours(12.5);
                $taskAutomation = new Task()
                    ->setProject($project)
                    ->setSprint($sprint)
                    ->setTitle('Automatiser les relances')
                    ->setDescription('Créer les séquences mails selon la probabilité de closing')
                    ->setStatus(TaskStatus::TODO)
                    ->setPriority(TaskPriority::CRITICAL)
                    ->setDueAt(new DateTimeImmutable('+5 days'))
                    ->setEstimatedHours(18.0);

                $manager->persist($taskBacklog);
                $manager->persist($taskAutomation);

                $manager->persist(
                    new TaskRequest()
                        ->setTask($taskBacklog)
                        ->setTitle('Prioriser les leads chauds')
                        ->setDescription('Ajouter une règle SLA pour les leads > 80%')
                        ->setStatus(TaskRequestStatus::PENDING),
                );

                $manager->persist(
                    new TaskRequest()
                        ->setTask($taskAutomation)
                        ->setTitle('Valider le workflow de notifications')
                        ->setDescription('Valider la conformité RGPD avant diffusion')
                        ->setStatus(TaskRequestStatus::APPROVED)
                        ->setResolvedAt(new DateTimeImmutable('-1 day')),
                );
            }
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 9;
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
