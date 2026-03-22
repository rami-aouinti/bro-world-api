<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\DataFixtures\ORM;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Enum\BlogType;
use App\Crm\Domain\Entity\Billing;
use App\Crm\Domain\Entity\Company;
use App\Crm\Domain\Entity\Contact;
use App\Crm\Domain\Entity\Crm;
use App\Crm\Domain\Entity\CrmRepository;
use App\Crm\Domain\Entity\Employee;
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
use App\Platform\Domain\Entity\ApplicationPlugin;
use App\Platform\Domain\Entity\Plugin;
use App\Platform\Domain\Enum\PlatformKey;
use App\Platform\Domain\Enum\PluginKey;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Override;

final class LoadCrmData extends Fixture implements OrderedFixtureInterface
{
    private const int FAKER_SEED = 14021991;

    private const string DEFAULT_VOLUME = 'medium';

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

    /**
     * @var array<non-empty-string, array{
     *     companies:int,
     *     contactsPerCompany:int,
     *     employeesPerCompany:int,
     *     projectsPerCompany:int,
     *     sprintsPerProject:int,
     *     tasksPerSprint:int,
     *     taskRequestsPerTask:int,
     *     billingsPerCompany:int,
     *     projectAttachments:int,
     *     taskAttachments:int,
     *     wikiPagesPerProject:int
     * }>
     */
    private const array VOLUME_PROFILES = [
        'small' => [
            'companies' => 2,
            'contactsPerCompany' => 1,
            'employeesPerCompany' => 1,
            'projectsPerCompany' => 1,
            'sprintsPerProject' => 1,
            'tasksPerSprint' => 2,
            'taskRequestsPerTask' => 1,
            'billingsPerCompany' => 1,
            'projectAttachments' => 1,
            'taskAttachments' => 1,
            'wikiPagesPerProject' => 1,
        ],
        'medium' => [
            'companies' => 4,
            'contactsPerCompany' => 2,
            'employeesPerCompany' => 2,
            'projectsPerCompany' => 2,
            'sprintsPerProject' => 2,
            'tasksPerSprint' => 3,
            'taskRequestsPerTask' => 2,
            'billingsPerCompany' => 2,
            'projectAttachments' => 2,
            'taskAttachments' => 2,
            'wikiPagesPerProject' => 2,
        ],
        'large' => [
            'companies' => 8,
            'contactsPerCompany' => 4,
            'employeesPerCompany' => 3,
            'projectsPerCompany' => 3,
            'sprintsPerProject' => 3,
            'tasksPerSprint' => 5,
            'taskRequestsPerTask' => 3,
            'billingsPerCompany' => 3,
            'projectAttachments' => 3,
            'taskAttachments' => 3,
            'wikiPagesPerProject' => 4,
        ],
    ];

    #[Override]
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $faker->seed(self::FAKER_SEED);

        $profile = self::VOLUME_PROFILES[$this->resolveVolume()] ?? self::VOLUME_PROFILES[self::DEFAULT_VOLUME];
        $crmUsers = $this->getCrmUsers($manager);

        foreach ($this->getApplicationsByPlatform(PlatformKey::CRM) as $application) {
            $applicationHasBlogPlugin = $this->applicationHasBlogPlugin($manager, $application);
            $crm = $this->findOrCreateCrm($manager, $application);

            // Companies
            $companies = $this->generateCompanies($manager, $faker, $crm, $application, $profile['companies']);

            foreach ($companies as $companyIndex => $company) {
                // Contacts
                $this->generateContacts($manager, $faker, $crm, $company, $profile['contactsPerCompany']);

                // Employees
                $this->generateEmployees($manager, $faker, $crm, $profile['employeesPerCompany'], $crmUsers);

                // Projects
                $projects = $this->generateProjects(
                    $manager,
                    $faker,
                    $company,
                    $application,
                    $companyIndex,
                    $profile['projectsPerCompany'],
                    $profile['projectAttachments'],
                    $profile['wikiPagesPerProject'],
                );

                foreach ($projects as $project) {
                    // Sprints
                    $sprints = $this->generateSprints($manager, $faker, $project, $profile['sprintsPerProject']);

                    foreach ($sprints as $sprint) {
                        // Tasks
                        $tasks = $this->generateTasks(
                            $manager,
                            $faker,
                            $application,
                            $project,
                            $sprint,
                            $profile['tasksPerSprint'],
                            $profile['taskAttachments'],
                            $applicationHasBlogPlugin,
                        );

                        // Task requests
                        $this->generateTaskRequests($manager, $faker, $application, $tasks, $profile['taskRequestsPerTask']);
                    }
                }

                // Billings
                $this->generateBillings($manager, $faker, $company, $profile['billingsPerCompany']);
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

    private function findOrCreateCrm(ObjectManager $manager, Application $application): Crm
    {
        /** @var Crm|null $crm */
        $crm = $manager->getRepository(Crm::class)->findOneBy([
            'application' => $application,
        ]);

        if (!$crm instanceof Crm) {
            $crm = (new Crm())->setApplication($application);
            $manager->persist($crm);
        }

        return $crm;
    }

    /**
     * @return array<int, Company>
     */
    private function generateCompanies(
        ObjectManager $manager,
        Generator $faker,
        Crm $crm,
        Application $application,
        int $count,
    ): array {
        $companies = [];

        for ($index = 0; $index < $count; $index++) {
            $company = (new Company())
                ->setCrm($crm)
                ->setName(sprintf('%s - %s', $application->getTitle(), $faker->company()))
                ->setIndustry($faker->randomElement(['SaaS', 'Consulting', 'Retail', 'Finance', 'Healthcare', 'Education']))
                ->setWebsite($faker->url())
                ->setContactEmail($faker->companyEmail())
                ->setPhone($faker->e164PhoneNumber());

            $manager->persist($company);
            $companies[] = $company;
        }

        return $companies;
    }

    private function generateContacts(ObjectManager $manager, Generator $faker, Crm $crm, Company $company, int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            $contact = (new Contact())
                ->setCrm($crm)
                ->setCompany($company)
                ->setFirstName($faker->firstName())
                ->setLastName($faker->lastName())
                ->setEmail($faker->safeEmail())
                ->setPhone($faker->e164PhoneNumber())
                ->setJobTitle($faker->jobTitle())
                ->setCity($faker->city())
                ->setScore($faker->numberBetween(45, 100));

            $manager->persist($contact);
        }
    }

    /**
     * @param array<int, User> $crmUsers
     */
    private function generateEmployees(ObjectManager $manager, Generator $faker, Crm $crm, int $count, array $crmUsers): void
    {
        $crmUserCount = count($crmUsers);

        for ($index = 0; $index < $count; $index++) {
            $employee = (new Employee())
                ->setCrm($crm)
                ->setFirstName($faker->firstName())
                ->setLastName($faker->lastName())
                ->setEmail($faker->companyEmail())
                ->setPositionName($faker->jobTitle())
                ->setRoleName($faker->randomElement(['sales', 'support', 'manager', 'finance']));

            if ($crmUserCount > 0) {
                $employee->setUser($crmUsers[$index % $crmUserCount]);
            }

            $manager->persist($employee);
        }
    }

    /**
     * @return array<int, Project>
     */
    private function generateProjects(
        ObjectManager $manager,
        Generator $faker,
        Company $company,
        Application $application,
        int $companyIndex,
        int $projectCount,
        int $attachmentCount,
        int $wikiPageCount,
    ): array {
        $projects = [];

        for ($index = 0; $index < $projectCount; $index++) {
            $startedAt = $faker->dateTimeBetween('-3 months', '-2 weeks');
            $dueAt = $faker->dateTimeBetween($startedAt, '+4 months');
            $projectSlug = trim(strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $faker->words(2, true))), '-');
            if ($projectSlug === '') {
                $projectSlug = sprintf('project-%d-%d', $companyIndex + 1, $index + 1);
            }

            $project = (new Project())
                ->setCompany($company)
                ->setName(sprintf('%s - %s', $application->getTitle(), ucfirst($faker->words(3, true))))
                ->setCode(sprintf('PRJ-%d-%02d', $companyIndex + 1, $index + 1))
                ->setDescription($faker->paragraph(2))
                ->setStatus($faker->randomElement(ProjectStatus::cases()))
                ->setStartedAt(DateTimeImmutable::createFromMutable($startedAt))
                ->setDueAt(DateTimeImmutable::createFromMutable($dueAt))
                ->setGithubToken('ghp_john_root_fake_token')
                ->setGithubRepositories([
                    [
                        'fullName' => sprintf('rami-aouinti/%s-api', $projectSlug),
                        'defaultBranch' => 'main',
                    ],
                    [
                        'fullName' => sprintf('rami-aouinti/%s-web', $projectSlug),
                        'defaultBranch' => 'develop',
                    ],
                ]);

            for ($attachmentIndex = 0; $attachmentIndex < $attachmentCount; $attachmentIndex++) {
                $project->addAttachment($this->generateAttachment($faker, '/uploads/crm/projects/', $project->getId()));
            }

            for ($wikiIndex = 0; $wikiIndex < $wikiPageCount; $wikiIndex++) {
                $project->addWikiPage($this->generateWikiPage($faker));
            }

            $manager->persist($project);
            $projects[] = $project;
        }

        return $projects;
    }

    /**
     * @return array<int, Sprint>
     */
    private function generateSprints(ObjectManager $manager, Generator $faker, Project $project, int $count): array
    {
        $sprints = [];

        for ($index = 0; $index < $count; $index++) {
            $startDate = $faker->dateTimeBetween('-6 weeks', '+2 weeks');
            $endDate = $faker->dateTimeBetween($startDate, '+4 weeks');

            $sprint = (new Sprint())
                ->setProject($project)
                ->setName(sprintf('Sprint %d - %s', $index + 1, $faker->word()))
                ->setGoal($faker->sentence(8))
                ->setStatus($faker->randomElement(SprintStatus::cases()))
                ->setStartDate(DateTimeImmutable::createFromMutable($startDate))
                ->setEndDate(DateTimeImmutable::createFromMutable($endDate));

            $manager->persist($sprint);
            $sprints[] = $sprint;
        }

        return $sprints;
    }

    /**
     * @return array<int, Task>
     */
    private function generateTasks(
        ObjectManager $manager,
        Generator $faker,
        Application $application,
        Project $project,
        Sprint $sprint,
        int $count,
        int $attachmentCount,
        bool $applicationHasBlogPlugin,
    ): array {
        $tasks = [];

        for ($index = 0; $index < $count; $index++) {
            $task = (new Task())
                ->setProject($project)
                ->setSprint($sprint)
                ->setTitle($faker->sentence(5))
                ->setDescription($faker->paragraph(2))
                ->setStatus($faker->randomElement(TaskStatus::cases()))
                ->setPriority($faker->randomElement(TaskPriority::cases()))
                ->setDueAt(DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-1 week', '+2 months')))
                ->setEstimatedHours((float)$faker->randomFloat(1, 2, 30));

            for ($attachmentIndex = 0; $attachmentIndex < $attachmentCount; $attachmentIndex++) {
                $task->addAttachment($this->generateAttachment($faker, '/uploads/crm/tasks/', $task->getId()));
            }

            if ($applicationHasBlogPlugin) {
                $task->setBlog($this->createBlogThreadForEntity($manager, $faker, $application, 'task', $task->getId(), $task->getTitle()));
            }

            $manager->persist($task);
            $tasks[] = $task;
        }

        return $tasks;
    }

    /**
     * @param array<int, Task> $tasks
     */
    private function generateTaskRequests(
        ObjectManager $manager,
        Generator $faker,
        Application $application,
        array $tasks,
        int $countByTask,
    ): void {
        foreach ($tasks as $task) {
            $repository = $task->getProject()?->getRepositories()->first();
            if (!$repository instanceof CrmRepository) {
                $project = $task->getProject();
                if (!$project instanceof Project) {
                    continue;
                }

                $repository = (new CrmRepository())
                    ->setProject($project)
                    ->setProvider('github')
                    ->setOwner('fixtures')
                    ->setName($project->getCode() !== null ? strtolower($project->getCode()) : 'project-' . substr($project->getId(), 0, 8))
                    ->setFullName(sprintf('fixtures/%s', $project->getCode() !== null ? strtolower($project->getCode()) : 'project-' . substr($project->getId(), 0, 8)))
                    ->setDefaultBranch('main')
                    ->setIsPrivate(false)
                    ->setSyncStatus('synced');

                $project->addRepository($repository);
                $manager->persist($repository);
            }

            for ($index = 0; $index < $countByTask; $index++) {
                $status = $faker->randomElement(TaskRequestStatus::cases());
                $taskRequest = (new TaskRequest())
                    ->setTask($task)
                    ->setRepository($repository)
                    ->setTitle($faker->sentence(6))
                    ->setDescription($faker->paragraph())
                    ->setStatus($status);

                $taskRequest->setBlog($this->createBlogThreadForEntity(
                    $manager,
                    $faker,
                    $application,
                    'task-request',
                    $taskRequest->getId(),
                    $taskRequest->getTitle(),
                ));

                if (in_array($status, [TaskRequestStatus::APPROVED, TaskRequestStatus::DONE, TaskRequestStatus::REJECTED], true)) {
                    $taskRequest->setResolvedAt(DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-2 weeks', 'now')));
                }

                $manager->persist($taskRequest);
            }
        }
    }

    private function generateBillings(ObjectManager $manager, Generator $faker, Company $company, int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            $billing = (new Billing())
                ->setCompany($company)
                ->setLabel('Abonnement CRM - ' . $faker->words(2, true))
                ->setAmount((float)$faker->randomFloat(2, 499, 12000))
                ->setCurrency($faker->randomElement(['EUR', 'USD', 'GBP']))
                ->setStatus($faker->randomElement(['paid', 'pending', 'overdue']))
                ->setDueAt(DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-10 days', '+40 days')));

            $manager->persist($billing);
        }
    }

    /**
     * @return array<string, int|string>
     */
    private function generateAttachment(Generator $faker, string $basePath, string $entityId): array
    {
        $mimeByExtension = [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        $extension = $faker->randomElement(array_keys($mimeByExtension));
        $name = sprintf('%s-%s.%s', $faker->slug(2), $faker->bothify('##??'), $extension);

        return [
            'url' => sprintf('%s%s/%s', $basePath, $entityId, $name),
            'originalName' => $name,
            'mimeType' => $mimeByExtension[$extension],
            'size' => $faker->numberBetween(10_000, 5_000_000),
            'extension' => $extension,
            'uploadedAt' => DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-3 months', 'now'))->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function generateWikiPage(Generator $faker): array
    {
        return [
            'id' => str_replace('-', '', $faker->uuid()),
            'title' => ucfirst($faker->words(4, true)),
            'content' => $faker->paragraphs(3, true),
            'createdAt' => DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-4 months', 'now'))->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<int, User>
     */
    private function getCrmUsers(ObjectManager $manager): array
    {
        /** @var array<int, User> $users */
        $users = $manager->getRepository(User::class)->createQueryBuilder('u')
            ->select('DISTINCT u')
            ->innerJoin('u.userGroups', 'ug')
            ->innerJoin('ug.role', 'r')
            ->where('r.id LIKE :crmPrefix')
            ->setParameter('crmPrefix', 'ROLE_CRM_%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();

        return $users;
    }

    private function resolveVolume(): string
    {
        $volume = strtolower((string)($_ENV['CRM_FIXTURE_VOLUME'] ?? $_SERVER['CRM_FIXTURE_VOLUME'] ?? getenv('CRM_FIXTURE_VOLUME') ?: self::DEFAULT_VOLUME));

        return array_key_exists($volume, self::VOLUME_PROFILES) ? $volume : self::DEFAULT_VOLUME;
    }

    private function applicationHasBlogPlugin(ObjectManager $manager, Application $application): bool
    {
        $blogPlugin = $this->getReference('Plugin-Knowledge-Base-Connector', Plugin::class);

        if (!$blogPlugin instanceof Plugin) {
            return false;
        }

        if ($blogPlugin->getPluginKey() !== PluginKey::BLOG) {
            return false;
        }

        return $manager->getRepository(ApplicationPlugin::class)->findOneBy([
            'application' => $application,
            'plugin' => $blogPlugin,
        ]) !== null;
    }

    private function createBlogThreadForEntity(
        ObjectManager $manager,
        Generator $faker,
        Application $application,
        string $scope,
        string $scopeId,
        string $title,
    ): Blog {
        $identifier = strtolower(str_replace('-', '', $scopeId));
        $blog = (new Blog())
            ->setApplication($application)
            ->setOwner($application->getUser())
            ->setType(BlogType::APPLICATION)
            ->setTitle(sprintf('CRM %s blog %s', ucfirst($scope), $title))
            ->setSlug(sprintf('crm-%s-%s', $scope, substr($identifier, 0, 18)))
            ->setDescription(sprintf('Fil de discussion CRM pour %s', $title));

        $rootPost = (new BlogPost())
            ->setBlog($blog)
            ->setAuthor($application->getUser())
            ->setTitle(sprintf('%s - contexte', $title))
            ->setSlug(sprintf('post-root-%s-%s', $scope, substr($identifier, 0, 12)))
            ->setContent($faker->paragraph(2));

        $followUpPost = (new BlogPost())
            ->setBlog($blog)
            ->setAuthor($application->getUser())
            ->setTitle(sprintf('%s - suivi', $title))
            ->setSlug(sprintf('post-followup-%s-%s', $scope, substr($identifier, 0, 12)))
            ->setContent($faker->paragraph(2));

        $parentComment = (new BlogComment())
            ->setPost($rootPost)
            ->setAuthor($application->getUser())
            ->setContent('Commentaire initial de validation endpoint détail.');
        $childComment = (new BlogComment())
            ->setPost($rootPost)
            ->setAuthor($application->getUser())
            ->setContent('Réponse de suivi pour valider les commentaires imbriqués.')
            ->setParent($parentComment);
        $subChildComment = (new BlogComment())
            ->setPost($rootPost)
            ->setAuthor($application->getUser())
            ->setContent('Sous-réponse pour vérifier le 3e niveau de thread.')
            ->setParent($childComment);

        $manager->persist($rootPost);
        $manager->persist($followUpPost);
        $manager->persist($parentComment);
        $manager->persist($childComment);
        $manager->persist($subChildComment);

        return $blog;
    }
}
