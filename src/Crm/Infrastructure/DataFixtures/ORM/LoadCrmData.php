<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\DataFixtures\ORM;

use App\Crm\Domain\Entity\Company;
use App\Crm\Domain\Entity\Crm;
use App\Crm\Domain\Entity\Project;
use App\Crm\Domain\Entity\Sprint;
use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Entity\TaskRequest;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadCrmData extends Fixture implements OrderedFixtureInterface
{
    /** @var array<non-empty-string, array<int, non-empty-string>> */
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
            $crm = (new Crm())->setApplication($application);
            $manager->persist($crm);

            $companies = [
                (new Company())->setCrm($crm)->setName($application->getTitle() . ' - Acme Corp'),
                (new Company())->setCrm($crm)->setName($application->getTitle() . ' - Globex'),
            ];

            foreach ($companies as $companyIndex => $company) {
                $manager->persist($company);

                $project = (new Project())
                    ->setCompany($company)
                    ->setName($company->getName() . ' - Projet Transformation');
                $manager->persist($project);

                $sprint = (new Sprint())
                    ->setProject($project)
                    ->setName('Sprint ' . (string) ($companyIndex + 1));
                $manager->persist($sprint);

                $taskBacklog = (new Task())
                    ->setProject($project)
                    ->setSprint($sprint)
                    ->setTitle('Consolider le backlog');
                $taskAutomation = (new Task())
                    ->setProject($project)
                    ->setSprint($sprint)
                    ->setTitle('Automatiser les relances');

                $manager->persist($taskBacklog);
                $manager->persist($taskAutomation);

                $manager->persist(
                    (new TaskRequest())
                        ->setTask($taskBacklog)
                        ->setTitle('Prioriser les leads chauds')
                        ->setStatus('pending'),
                );

                $manager->persist(
                    (new TaskRequest())
                        ->setTask($taskAutomation)
                        ->setTitle('Valider le workflow de notifications')
                        ->setStatus('approved'),
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

    /** @return array<int, Application> */
    private function getApplicationsByPlatform(PlatformKey $platformKey): array
    {
        $applications = [];

        foreach (self::APPLICATION_KEYS_BY_PLATFORM[$platformKey->value] ?? [] as $applicationKey) {
            $applications[] = $this->getReference('Application-' . $applicationKey, Application::class);
        }

        return $applications;
    }
}
