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

use function sprintf;

final class LoadCrmData extends Fixture implements OrderedFixtureInterface
{
    private const int COMPANY_COUNT_PER_APPLICATION = 2;
    private const int PROJECT_COUNT_PER_COMPANY = 2;
    private const int TASK_COUNT_PER_PROJECT = 3;

    #[Override]
    public function load(ObjectManager $manager): void
    {
        $crmApplications = $manager->getRepository(Application::class)
            ->createQueryBuilder('application')
            ->innerJoin('application.platform', 'platform')
            ->andWhere('platform.platformKey = :platformKey')
            ->setParameter('platformKey', PlatformKey::CRM)
            ->orderBy('application.title', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($crmApplications as $applicationIndex => $application) {
            if (!$application instanceof Application) {
                continue;
            }

            $crm = $manager->getRepository(Crm::class)->findOneBy([
                'application' => $application,
            ]);

            if (!$crm instanceof Crm) {
                $crm = (new Crm())->setApplication($application);
                $manager->persist($crm);
            }

            for ($companyIndex = 1; $companyIndex <= self::COMPANY_COUNT_PER_APPLICATION; ++$companyIndex) {
                $companyName = sprintf('%s - Company %d', $application->getTitle(), $companyIndex);
                $company = $manager->getRepository(Company::class)->findOneBy([
                    'crm' => $crm,
                    'name' => $companyName,
                ]);

                if (!$company instanceof Company) {
                    $company = (new Company())
                        ->setCrm($crm)
                        ->setName($companyName);
                    $manager->persist($company);
                }

                for ($projectIndex = 1; $projectIndex <= self::PROJECT_COUNT_PER_COMPANY; ++$projectIndex) {
                    $projectName = sprintf('%s - Project %d', $companyName, $projectIndex);
                    $project = $manager->getRepository(Project::class)->findOneBy([
                        'company' => $company,
                        'name' => $projectName,
                    ]);

                    if (!$project instanceof Project) {
                        $project = (new Project())
                            ->setCompany($company)
                            ->setName($projectName);
                        $manager->persist($project);
                    }

                    $sprintName = sprintf('%s - Sprint 1', $projectName);
                    $sprint = $manager->getRepository(Sprint::class)->findOneBy([
                        'project' => $project,
                        'name' => $sprintName,
                    ]);

                    if (!$sprint instanceof Sprint) {
                        $sprint = (new Sprint())
                            ->setProject($project)
                            ->setName($sprintName);
                        $manager->persist($sprint);
                    }

                    for ($taskIndex = 1; $taskIndex <= self::TASK_COUNT_PER_PROJECT; ++$taskIndex) {
                        $taskTitle = sprintf('%s - Task %d', $projectName, $taskIndex);
                        $task = $manager->getRepository(Task::class)->findOneBy([
                            'project' => $project,
                            'title' => $taskTitle,
                        ]);

                        if (!$task instanceof Task) {
                            $task = (new Task())
                                ->setProject($project)
                                ->setSprint($sprint)
                                ->setTitle($taskTitle);
                            $manager->persist($task);
                        }

                        $taskRequestTitle = sprintf('%s - Request 1', $taskTitle);
                        $taskRequest = $manager->getRepository(TaskRequest::class)->findOneBy([
                            'task' => $task,
                            'title' => $taskRequestTitle,
                        ]);

                        if (!$taskRequest instanceof TaskRequest) {
                            $taskRequest = (new TaskRequest())
                                ->setTask($task)
                                ->setTitle($taskRequestTitle)
                                ->setStatus(($applicationIndex + $taskIndex) % 2 === 0 ? 'pending' : 'approved');
                            $manager->persist($taskRequest);
                        }
                    }
                }
            }
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 8;
    }
}
