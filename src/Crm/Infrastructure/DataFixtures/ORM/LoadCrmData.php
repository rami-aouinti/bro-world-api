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
use App\Crm\Domain\Entity\TaskRequestWorklog;
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
     * @var array<int, array{
     *     userReference: non-empty-string,
     *     firstName: non-empty-string,
     *     lastName: non-empty-string,
     *     email: non-empty-string,
     *     positionName: non-empty-string,
     *     roleName: non-empty-string
     * }>
     */
    private const array DETERMINISTIC_EMPLOYEES = [
        [
            'userReference' => 'User-john-root',
            'firstName' => 'John',
            'lastName' => 'Root',
            'email' => 'john-root@crm.example.test',
            'positionName' => 'Owner',
            'roleName' => 'owner',
        ],
        [
            'userReference' => 'User-john-admin',
            'firstName' => 'John',
            'lastName' => 'Admin',
            'email' => 'john-admin@crm.example.test',
            'positionName' => 'CRM Administrator',
            'roleName' => 'admin',
        ],
        [
            'userReference' => 'User-john-user',
            'firstName' => 'John',
            'lastName' => 'User',
            'email' => 'john-user@crm.example.test',
            'positionName' => 'Sales Representative',
            'roleName' => 'sales',
        ],
        [
            'userReference' => 'User-john-api',
            'firstName' => 'John',
            'lastName' => 'Api',
            'email' => 'john-api@crm.example.test',
            'positionName' => 'API Operator',
            'roleName' => 'api',
        ],
    ];

    /**
     * @var array<int, array{
     *     name: non-empty-string,
     *     code: non-empty-string,
     *     slug: non-empty-string,
     *     status: ProjectStatus,
     *     assignees: array<int, non-empty-string>
     * }>
     */
    private const array BASE_PROJECTS = [
        [
            'name' => 'Bro World',
            'code' => 'PRJ-BRO',
            'slug' => 'bro-world',
            'status' => ProjectStatus::ACTIVE,
            'assignees' => ['User-john-root', 'User-john-admin', 'User-john-user'],
        ],
        [
            'name' => 'Shopware World',
            'code' => 'PRJ-SHOP',
            'slug' => 'shopware-world',
            'status' => ProjectStatus::PLANNED,
            'assignees' => ['User-john-root', 'User-john-admin', 'User-john-api'],
        ],
        [
            'name' => 'Oro World',
            'code' => 'PRJ-ORO',
            'slug' => 'oro-world',
            'status' => ProjectStatus::ON_HOLD,
            'assignees' => ['User-john-root', 'User-john-user', 'User-john-api'],
        ],
    ];

    /**
     * @var array<non-empty-string, array<int, non-empty-string>>
     */
    private const array APPLICATION_KEYS_BY_PLATFORM = [
        PlatformKey::CRM->value => [
            'crm-sales-hub',
            'crm-pipeline-pro',
            'crm-support-desk',
            'crm-general-core',
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
        $generalOwner = $this->getReference('User-john-root', User::class);

        $profile = self::VOLUME_PROFILES[$this->resolveVolume()] ?? self::VOLUME_PROFILES[self::DEFAULT_VOLUME];

        foreach ($this->getApplicationsByPlatform(PlatformKey::CRM) as $application) {
            $applicationHasBlogPlugin = $this->applicationHasBlogPlugin($manager, $application);
            $crm = $this->findOrCreateCrm($manager, $application);
            $applicationKey = $application->getSlug();
            $this->addReference('Crm-' . $applicationKey, $crm);
            if ($applicationKey === 'crm-general-core') {
                $this->addReference('Crm-General-Core', $crm);
            }

            // Companies
            $companies = $this->generateCompanies($manager, $faker, $crm, $application, $profile['companies']);
            if ($companies !== []) {
                $this->addReference('Crm-Company-' . $applicationKey . '-1', $companies[0]);
            }

            // Employees
            $this->generateEmployees($manager, $crm);

            foreach ($companies as $companyIndex => $company) {
                // Contacts
                $this->generateContacts($manager, $faker, $crm, $company, $profile['contactsPerCompany']);

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
                if ($projects !== []) {
                    $this->addReference('Crm-Project-' . $applicationKey . '-' . ($companyIndex + 1) . '-1', $projects[0]);
                }

                foreach ($projects as $project) {
                    // Sprints
                    $sprints = $this->generateSprints($manager, $faker, $project, $profile['sprintsPerProject']);

                    foreach ($sprints as $sprintIndex => $sprint) {
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
                        if ($tasks !== []) {
                            $this->addReference(
                                'Crm-Task-' . $applicationKey . '-' . ($companyIndex + 1) . '-' . $project->getCode() . '-S' . ($sprintIndex + 1) . '-T1',
                                $tasks[0],
                            );
                        }

                        // Task requests
                        $this->generateTaskRequests($manager, $faker, $application, $tasks, $profile['taskRequestsPerTask']);
                    }
                }

                // Billings
                $this->generateBillings($manager, $faker, $company, $profile['billingsPerCompany']);
            }
        }

        if ($generalOwner instanceof User) {
            $this->addReference('Crm-General-Owner', $generalOwner);
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

    private function generateEmployees(ObjectManager $manager, Crm $crm): void
    {
        foreach (self::DETERMINISTIC_EMPLOYEES as $employeeData) {
            $employee = (new Employee())
                ->setCrm($crm)
                ->setUser($this->getReference($employeeData['userReference'], User::class))
                ->setFirstName($employeeData['firstName'])
                ->setLastName($employeeData['lastName'])
                ->setEmail($employeeData['email'])
                ->setPositionName($employeeData['positionName'])
                ->setRoleName($employeeData['roleName']);

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
        $useBaseProjects = $application->getSlug() === 'crm-general-core' && $companyIndex === 0;
        $effectiveProjectCount = $useBaseProjects ? max($projectCount, count(self::BASE_PROJECTS)) : $projectCount;

        for ($index = 0; $index < $effectiveProjectCount; $index++) {
            $startedAt = $faker->dateTimeBetween('-3 months', '-2 weeks');
            $dueAt = $faker->dateTimeBetween($startedAt, '+4 months');
            $projectSlug = trim(strtolower((string)preg_replace('/[^a-z0-9]+/i', '-', $faker->words(2, true))), '-');
            if ($projectSlug === '') {
                $projectSlug = sprintf('project-%d-%d', $companyIndex + 1, $index + 1);
            }
            $projectName = sprintf('%s - %s', $application->getTitle(), ucfirst($faker->words(3, true)));
            $projectCode = sprintf('PRJ-%d-%02d', $companyIndex + 1, $index + 1);
            $projectStatus = $faker->randomElement(ProjectStatus::cases());
            $projectAssignees = [];

            if ($useBaseProjects && isset(self::BASE_PROJECTS[$index])) {
                $baseProject = self::BASE_PROJECTS[$index];
                $projectSlug = $baseProject['slug'];
                $projectName = $baseProject['name'];
                $projectCode = $baseProject['code'];
                $projectStatus = $baseProject['status'];
                $projectAssignees = $baseProject['assignees'];
            }

            $project = (new Project())
                ->setCompany($company)
                ->setName($projectName)
                ->setCode($projectCode)
                ->setDescription($faker->paragraph(2))
                ->setStatus($projectStatus)
                ->setStartedAt(DateTimeImmutable::createFromMutable($startedAt))
                ->setDueAt(DateTimeImmutable::createFromMutable($dueAt))
                ->setGithubToken('ghp_john_root_fake_token')
                ->setGithubRepositories([
                    [
                        'fullName' => sprintf('john-root/%s-api', $projectSlug),
                        'defaultBranch' => 'main',
                    ],
                    [
                        'fullName' => sprintf('john-root/%s-web', $projectSlug),
                        'defaultBranch' => 'develop',
                    ],
                ]);

            foreach ($projectAssignees as $projectAssigneeReference) {
                $project->addAssignee($this->getReference($projectAssigneeReference, User::class));
            }

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
        if ($this->isBroWorldProject($project)) {
            return $this->generateBroWorldSprints($manager, $project, $count);
        }

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
        $broWorldAssignees = $this->isBroWorldProject($project) ? $this->getCanonicalUsers() : [];
        $sprintStartDate = $sprint->getStartDate();
        $sprintEndDate = $sprint->getEndDate();

        for ($index = 0; $index < $count; $index++) {
            $taskStatus = $faker->randomElement(TaskStatus::cases());
            $taskPriority = $faker->randomElement(TaskPriority::cases());
            $taskTitle = $faker->sentence(5);
            $taskDescription = $faker->paragraph(2);
            $dueAt = DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-1 week', '+2 months'));
            if ($this->isBroWorldProject($project)) {
                $taskStatus = match (true) {
                    $sprint->getStatus() === SprintStatus::CLOSED => $index === $count - 1 ? TaskStatus::DONE : TaskStatus::IN_PROGRESS,
                    $sprint->getStatus() === SprintStatus::ACTIVE => $index === 0 ? TaskStatus::IN_PROGRESS : TaskStatus::TODO,
                    default => TaskStatus::TODO,
                };
                $taskPriority = match ($index % 4) {
                    0 => TaskPriority::CRITICAL,
                    1 => TaskPriority::HIGH,
                    2 => TaskPriority::MEDIUM,
                    default => TaskPriority::LOW,
                };
                $taskTitle = sprintf('Bro World - %s task %d', $sprint->getName(), $index + 1);
                $taskDescription = sprintf(
                    'Implement and validate %s task %d for Bro World with clear acceptance criteria.',
                    $sprint->getName(),
                    $index + 1,
                );
                if ($sprintStartDate instanceof DateTimeImmutable && $sprintEndDate instanceof DateTimeImmutable) {
                    $dueAt = $sprintStartDate->modify(sprintf('+%d days', min(12, ($index + 1) * 3)));
                    if ($dueAt > $sprintEndDate) {
                        $dueAt = $sprintEndDate;
                    }
                }
            }

            $task = (new Task())
                ->setProject($project)
                ->setSprint($sprint)
                ->setTitle($taskTitle)
                ->setDescription($taskDescription)
                ->setStatus($taskStatus)
                ->setPriority($taskPriority)
                ->setDueAt($dueAt)
                ->setEstimatedHours((float)$faker->randomFloat(1, 2, 30));

            if ($broWorldAssignees !== []) {
                $task->addAssignee($broWorldAssignees[$index % count($broWorldAssignees)]);
            }

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
        $applicationKey = $application->getSlug();
        $johnRoot = $this->getReference('User-john-root', User::class);
        $generalCorePlannedConsumedRemainingAdded = false;
        $generalCoreRootLoggedForEmployeeAdded = false;

        foreach ($tasks as $task) {
            $assignableEmployees = $this->getAssignableEmployeesForTask($manager, $task);
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
                $requestedAt = DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-2 months', 'now'));
                $title = $faker->sentence(6);
                $description = $faker->paragraph();
                $plannedHours = (float)$faker->randomElement([4, 8, 12, 16, 20, 24, 32]);
                if ($this->isBroWorldProject($task->getProject())) {
                    $title = sprintf('Bro World task request %d', $index + 1);
                    $description = sprintf(
                        'Deliver the implementation package for "%s" with tests and documentation.',
                        $task->getTitle(),
                    );
                    $status = match ($task->getStatus()) {
                        TaskStatus::DONE => TaskRequestStatus::DONE,
                        TaskStatus::IN_PROGRESS => TaskRequestStatus::PROGRESS,
                        TaskStatus::BLOCKED => TaskRequestStatus::REJECTED,
                        default => TaskRequestStatus::PENDING,
                    };
                    $taskDueAt = $task->getDueAt();
                    if ($taskDueAt instanceof DateTimeImmutable) {
                        $requestedAt = $taskDueAt->modify(sprintf('-%d days', 5 - min(4, $index)));
                    }
                    $plannedHours = (float)(8 + ($index * 4));
                }
                $taskRequest = (new TaskRequest())
                    ->setTask($task)
                    ->setRepository($repository)
                    ->setTitle($title)
                    ->setDescription($description)
                    ->setStatus($status)
                    ->setRequestedAt($requestedAt)
                    ->setPlannedHours($plannedHours);

                $assignedEmployee = $assignableEmployees !== []
                    ? $faker->randomElement($assignableEmployees)
                    : null;
                if ($this->isBroWorldProject($task->getProject()) && $assignableEmployees !== []) {
                    $assignedEmployee = $assignableEmployees[$index % count($assignableEmployees)];
                }
                if ($assignedEmployee instanceof Employee && $assignedEmployee->getUser() instanceof User) {
                    $taskRequest->addAssignee($assignedEmployee->getUser());
                }

                $taskRequest->setBlog($this->createBlogThreadForEntity(
                    $manager,
                    $faker,
                    $application,
                    'task-request',
                    $taskRequest->getId(),
                    $taskRequest->getTitle(),
                ));

                if (in_array($status, [TaskRequestStatus::APPROVED, TaskRequestStatus::DONE, TaskRequestStatus::REJECTED], true)) {
                    $resolvedAt = DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-2 weeks', 'now'));
                    if ($this->isBroWorldProject($task->getProject())) {
                        $resolvedAt = $requestedAt->modify('+3 days');
                    }
                    $taskRequest->setResolvedAt($resolvedAt);
                }

                if (
                    $applicationKey === 'crm-general-core'
                    && !$generalCorePlannedConsumedRemainingAdded
                    && $assignedEmployee instanceof Employee
                    && $assignedEmployee->getUser() instanceof User
                ) {
                    $taskRequest->setPlannedHours(24.0);
                    $taskRequest->addAssignee($assignedEmployee->getUser());
                    $assigneeWorklog = (new TaskRequestWorklog())
                        ->setTaskRequest($taskRequest)
                        ->setEmployee($assignedEmployee)
                        ->setLoggedByUser($assignedEmployee->getUser())
                        ->setHours(5.0)
                        ->setLoggedAt(DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-10 days', 'now')))
                        ->setComment('Avancement initial par employé assigné (5h).');
                    $taskRequest->addWorklog($assigneeWorklog);
                    $manager->persist($assigneeWorklog);
                    $this->addReference('Crm-TaskRequest-general-core-planned24-consumed5', $taskRequest);
                    $this->addReference('Crm-TaskRequestWorklog-general-core-assignee-5h', $assigneeWorklog);
                    $generalCorePlannedConsumedRemainingAdded = true;
                } elseif (
                    $applicationKey === 'crm-general-core'
                    && !$generalCoreRootLoggedForEmployeeAdded
                    && $assignedEmployee instanceof Employee
                    && $johnRoot instanceof User
                ) {
                    $rootWorklog = (new TaskRequestWorklog())
                        ->setTaskRequest($taskRequest)
                        ->setEmployee($assignedEmployee)
                        ->setLoggedByUser($johnRoot)
                        ->setHours(2.0)
                        ->setLoggedAt(DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-7 days', 'now')))
                        ->setComment('john-root saisit du temps pour un employé assigné sur cette demande.');
                    $taskRequest->addWorklog($rootWorklog);
                    $manager->persist($rootWorklog);
                    $this->addReference('Crm-TaskRequest-general-core-root-logs-for-employee', $taskRequest);
                    $this->addReference('Crm-TaskRequestWorklog-general-core-root-logs-for-employee', $rootWorklog);
                    $generalCoreRootLoggedForEmployeeAdded = true;
                }

                $manager->persist($taskRequest);
            }
        }
    }

    /**
     * @return array<int, Employee>
     */
    private function getAssignableEmployeesForTask(ObjectManager $manager, Task $task): array
    {
        $crm = $task->getProject()?->getCompany()?->getCrm();
        if (!$crm instanceof Crm) {
            return [];
        }

        /** @var array<int, Employee> $employees */
        $employees = $manager->getRepository(Employee::class)->findBy(['crm' => $crm]);

        return array_values(array_filter(
            $employees,
            static fn (Employee $employee): bool => $employee->getUser() instanceof User,
        ));
    }

    /**
     * @return array<int, Sprint>
     */
    private function generateBroWorldSprints(ObjectManager $manager, Project $project, int $count): array
    {
        $sprints = [];
        $effectiveCount = max(2, $count);
        $currentWeekStart = (new DateTimeImmutable('monday this week'))->setTime(0, 0);

        for ($index = 0; $index < $effectiveCount; $index++) {
            if ($index === $effectiveCount - 1) {
                $startDate = $currentWeekStart;
                $endDate = $currentWeekStart->modify('+13 days');
                $status = SprintStatus::ACTIVE;
            } else {
                $monthsAgo = 4 - min(1, $index);
                $startDate = $currentWeekStart
                    ->modify(sprintf('-%d months', $monthsAgo))
                    ->modify(sprintf('+%d days', $index * 14));
                $endDate = $startDate->modify('+13 days');
                $status = SprintStatus::CLOSED;
            }

            $sprint = (new Sprint())
                ->setProject($project)
                ->setName(sprintf('Sprint %d - Bro World Delivery Wave', $index + 1))
                ->setGoal(sprintf('Deliver Bro World objectives for iteration %d with stable milestone outcomes.', $index + 1))
                ->setStatus($status)
                ->setStartDate($startDate)
                ->setEndDate($endDate);

            foreach ($this->getCanonicalUsers() as $assignee) {
                $sprint->addAssignee($assignee);
            }

            $manager->persist($sprint);
            $sprints[] = $sprint;
        }

        return $sprints;
    }

    private function isBroWorldProject(?Project $project): bool
    {
        if (!$project instanceof Project) {
            return false;
        }

        return $project->getCode() === 'PRJ-BRO' || $project->getName() === 'Bro World';
    }

    /**
     * @return array<int, User>
     */
    private function getCanonicalUsers(): array
    {
        return [
            $this->getReference('User-john-root', User::class),
            $this->getReference('User-john-admin', User::class),
            $this->getReference('User-john-user', User::class),
            $this->getReference('User-john-api', User::class),
        ];
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
