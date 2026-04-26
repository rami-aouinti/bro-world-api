<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\DataFixtures\ORM;

use App\Calendar\Domain\Entity\Calendar;
use App\Calendar\Domain\Entity\Event;
use App\Calendar\Domain\Enum\EventStatus;
use App\Calendar\Domain\Enum\EventVisibility;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Enum\BlogType;
use App\Chat\Domain\Entity\Chat;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Domain\Enum\ConversationType;
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
     * @var array<int, array{
     *     name: non-empty-string,
     *     goal: non-empty-string,
     *     status: SprintStatus,
     *     startOffset: non-empty-string,
     *     durationDays: int
     * }>
     */
    private const array BRO_WORLD_SPRINTS = [
        [
            'name' => 'Sprint 1 - Platform Foundations',
            'goal' => 'Stabilize CRM General architecture and baseline delivery pipeline for Bro World.',
            'status' => SprintStatus::CLOSED,
            'startOffset' => '-4 months',
            'durationDays' => 14,
        ],
        [
            'name' => 'Sprint 2 - Product Integration',
            'goal' => 'Deliver integration milestones between CRM modules and business workflows.',
            'status' => SprintStatus::CLOSED,
            'startOffset' => '-3 months',
            'durationDays' => 14,
        ],
        [
            'name' => 'Sprint 3 - Current Delivery Week',
            'goal' => 'Finalize current week commitments and prepare production readiness checks.',
            'status' => SprintStatus::ACTIVE,
            'startOffset' => 'monday this week',
            'durationDays' => 14,
        ],
    ];

    /**
     * @var array<non-empty-string, array<int, array{
     *     title: non-empty-string,
     *     description: non-empty-string,
     *     status: TaskStatus,
     *     priority: TaskPriority,
     *     estimatedHours: float
     * }>>
     */
    private const array BRO_WORLD_TASKS_BY_SPRINT = [
        'Sprint 1 - Platform Foundations' => [
            [
                'title' => 'Define CRM General domain boundaries and API contracts',
                'description' => 'Document entities, relations, payload schemas and acceptance criteria for the first release.',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::CRITICAL,
                'estimatedHours' => 18.0,
            ],
            [
                'title' => 'Implement project and sprint seed orchestration',
                'description' => 'Create deterministic fixture generation for projects and sprint planning in CRM General.',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::HIGH,
                'estimatedHours' => 14.0,
            ],
            [
                'title' => 'Setup CI quality gates for CRM endpoints',
                'description' => 'Add static checks and baseline endpoint validation for CRM general controllers.',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::MEDIUM,
                'estimatedHours' => 10.0,
            ],
            [
                'title' => 'Publish delivery runbook for engineering onboarding',
                'description' => 'Provide onboarding and release checklist to ensure repeatable sprint execution.',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::LOW,
                'estimatedHours' => 8.0,
            ],
        ],
        'Sprint 2 - Product Integration' => [
            [
                'title' => 'Integrate task request workflow with repository metadata',
                'description' => 'Link task requests to project repositories and normalize sync metadata handling.',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::CRITICAL,
                'estimatedHours' => 16.0,
            ],
            [
                'title' => 'Build generalized reporting payload for CRM dashboard',
                'description' => 'Expose report structures for KPI panels and management summaries.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'estimatedHours' => 12.0,
            ],
            [
                'title' => 'Add assignment rules for owner and delivery team',
                'description' => 'Ensure John owner/admin/user/api assignment logic is applied across sprint tasks.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::MEDIUM,
                'estimatedHours' => 11.0,
            ],
            [
                'title' => 'Review API response consistency for frontend stores',
                'description' => 'Validate list/detail payloads used by Nuxt stores and ensure stable keys.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::LOW,
                'estimatedHours' => 7.0,
            ],
        ],
        'Sprint 3 - Current Delivery Week' => [
            [
                'title' => 'Finalize Bro World sprint board data for this week',
                'description' => 'Publish the current delivery board with status, assignees, and due dates for demo.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::CRITICAL,
                'estimatedHours' => 9.0,
            ],
            [
                'title' => 'Deliver task request timeline and worklog visibility',
                'description' => 'Expose request lifecycle and consumed/planned hours for operational follow-up.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::HIGH,
                'estimatedHours' => 12.0,
            ],
            [
                'title' => 'Run end-to-end smoke tests for CRM General flows',
                'description' => 'Validate create/update/list flow of projects, sprints, tasks, and task requests.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::MEDIUM,
                'estimatedHours' => 8.0,
            ],
            [
                'title' => 'Prepare stakeholder release summary in English',
                'description' => 'Provide release summary with delivered scope, risks, and next-step actions.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::LOW,
                'estimatedHours' => 6.0,
            ],
        ],
    ];

    /**
     * @var array<non-empty-string, array<int, non-empty-string>>
     */
    private const array APPLICATION_KEYS_BY_PLATFORM = [
        PlatformKey::CRM->value => [
            'crm-general-core',
        ],
    ];

    #[Override]
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('en_US');
        $faker->seed(self::FAKER_SEED);
        $generalOwner = $this->getReference('User-john-root', User::class);

        foreach ($this->getApplicationsByPlatform(PlatformKey::CRM) as $application) {
            $applicationHasBlogPlugin = $this->applicationHasBlogPlugin($manager, $application);
            $crm = $this->findOrCreateCrm($manager, $application);
            $applicationKey = $application->getSlug();
            $this->addReference('Crm-' . $applicationKey, $crm);
            if ($applicationKey === 'crm-general-core') {
                $this->addReference('Crm-General-Core', $crm);
            }

            // Companies
            $companies = $this->generateCompanies($manager, $faker, $crm, $application, 1);
            if ($companies !== []) {
                $this->addReference('Crm-Company-' . $applicationKey . '-1', $companies[0]);
            }

            // Employees
            $employees = $this->generateEmployees($manager, $crm);
            $this->ensureCrmGeneralChatAndCalendarScenario($manager, $crm, $application, $employees);

            foreach ($companies as $companyIndex => $company) {
                // Contacts
                $this->generateContacts($manager, $faker, $crm, $company, 2);

                // Projects
                $projects = $this->generateProjects(
                    $manager,
                    $faker,
                    $company,
                    $application,
                    $companyIndex,
                    count(self::BASE_PROJECTS),
                    1,
                    1,
                );
                if ($projects !== []) {
                    $this->addReference('Crm-Project-' . $applicationKey . '-' . ($companyIndex + 1) . '-1', $projects[0]);
                }

                foreach ($projects as $project) {
                    // Sprints
                    $sprints = $this->generateSprints($manager, $faker, $project, 3);

                    foreach ($sprints as $sprintIndex => $sprint) {
                        // Tasks
                        $tasks = $this->generateTasks(
                            $manager,
                            $faker,
                            $application,
                            $project,
                            $sprint,
                            4,
                            1,
                            $applicationHasBlogPlugin,
                        );
                        if ($tasks !== []) {
                            $this->addReference(
                                'Crm-Task-' . $applicationKey . '-' . ($companyIndex + 1) . '-' . $project->getCode() . '-S' . ($sprintIndex + 1) . '-T1',
                                $tasks[0],
                            );
                        }

                        // Task requests
                        $this->generateTaskRequests($manager, $faker, $application, $tasks, 2);
                    }
                }

                // Billings
                $this->generateBillings($manager, $faker, $company, 1);
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
            $companyName = $index === 0
                ? 'Bro World Group'
                : sprintf('%s - %s', $application->getTitle(), $faker->company());
            $company = (new Company())
                ->setCrm($crm)
                ->setName($companyName)
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
     * @return array<int, Employee>
     */
    private function generateEmployees(ObjectManager $manager, Crm $crm): array
    {
        $employees = [];
        foreach (self::DETERMINISTIC_EMPLOYEES as $employeeData) {
            $user = $this->getReference($employeeData['userReference'], User::class);
            $firstName = $employeeData['firstName'];
            $lastName = $employeeData['lastName'];
            if ($this->isBlank($firstName) && !$this->isBlank($user->getFirstName())) {
                $firstName = $user->getFirstName();
            }
            if ($this->isBlank($lastName) && !$this->isBlank($user->getLastName())) {
                $lastName = $user->getLastName();
            }

            $employee = (new Employee())
                ->setCrm($crm)
                ->setUser($user)
                ->setFirstName($firstName)
                ->setLastName($lastName)
                ->setEmail($employeeData['email'])
                ->setPositionName($employeeData['positionName'])
                ->setRoleName($employeeData['roleName']);

            $manager->persist($employee);
            $employees[] = $employee;
        }

        return $employees;
    }

    /**
     * @param array<int, Employee> $employees
     */
    private function ensureCrmGeneralChatAndCalendarScenario(ObjectManager $manager, Crm $crm, Application $application, array $employees): void
    {
        $application->ensureGeneratedSlug();
        if ($application->getSlug() !== 'crm-general-core' && $application->getTitle() !== 'CRM General Core') {
            return;
        }

        $chat = $this->ensureChat($manager, $application);
        $conversation = $this->ensureGeneralGroupConversation($manager, $chat);
        $this->ensureCrmEmployeeParticipants($manager, $crm, $conversation, $employees);

        $calendar = $this->ensureCalendar($manager, $application);
        $this->ensureCrmGeneralCalendarEvents($manager, $crm, $calendar, $employees);
    }

    private function ensureChat(ObjectManager $manager, Application $application): Chat
    {
        /** @var Chat|null $chat */
        $chat = $manager->getRepository(Chat::class)->findOneBy([
            'application' => $application,
        ]);

        if ($chat instanceof Chat) {
            return $chat;
        }

        $application->ensureGeneratedSlug();
        $chat = (new Chat())
            ->setApplication($application)
            ->setApplicationSlug($application->getSlug());

        $manager->persist($chat);

        return $chat;
    }

    private function ensureGeneralGroupConversation(ObjectManager $manager, Chat $chat): Conversation
    {
        /** @var Conversation|null $conversation */
        $conversation = $manager->getRepository(Conversation::class)->findOneBy([
            'chat' => $chat,
            'type' => ConversationType::GROUP,
            'title' => 'General Group',
        ]);

        if ($conversation instanceof Conversation) {
            return $conversation;
        }

        $conversation = (new Conversation())
            ->setChat($chat)
            ->setType(ConversationType::GROUP)
            ->setTitle('General Group');

        $manager->persist($conversation);

        return $conversation;
    }

    /**
     * @param array<int, Employee> $employees
     */
    private function ensureCrmEmployeeParticipants(ObjectManager $manager, Crm $crm, Conversation $conversation, array $employees): void
    {
        if ($employees === []) {
            /** @var array<int, Employee> $employees */
            $employees = $manager->getRepository(Employee::class)->findBy([
                'crm' => $crm,
            ]);
        }

        foreach ($employees as $employee) {
            $user = $employee->getUser();
            if (!$user instanceof User) {
                continue;
            }

            $existing = $manager->getRepository(ConversationParticipant::class)->findOneBy([
                'conversation' => $conversation,
                'user' => $user,
            ]);
            if ($existing instanceof ConversationParticipant) {
                continue;
            }

            $participant = (new ConversationParticipant())
                ->setConversation($conversation)
                ->setUser($user);

            $manager->persist($participant);
        }
    }

    private function ensureCalendar(ObjectManager $manager, Application $application): Calendar
    {
        /** @var Calendar|null $calendar */
        $calendar = $manager->getRepository(Calendar::class)->findOneBy([
            'application' => $application,
        ]);

        if ($calendar instanceof Calendar) {
            return $calendar;
        }

        $calendar = (new Calendar())
            ->setApplication($application)
            ->setUser($application->getUser())
            ->setTitle('CRM General Calendar');

        $manager->persist($calendar);

        return $calendar;
    }

    /**
     * @param array<int, Employee> $employees
     */
    private function ensureCrmGeneralCalendarEvents(ObjectManager $manager, Crm $crm, Calendar $calendar, array $employees): void
    {
        /** @var User|null $calendarOwner */
        $calendarOwner = $calendar->getUser();
        $this->ensureCalendarEvent(
            $manager,
            $calendar,
            'CRM General - Application Creation',
            'Milestone fixture for CRM General application creation.',
            $calendarOwner,
            2,
        );

        if ($employees === []) {
            /** @var array<int, Employee> $employees */
            $employees = $manager->getRepository(Employee::class)->findBy([
                'crm' => $crm,
            ]);
        }

        $dayOffset = 3;
        foreach ($employees as $employee) {
            $employeeUser = $employee->getUser();
            if (!$employeeUser instanceof User) {
                continue;
            }

            $this->ensureCalendarEvent(
                $manager,
                $calendar,
                sprintf('CRM General - %s %s Employee Start', $employee->getFirstName(), $employee->getLastName()),
                sprintf('%s %s commencement event as Employee for CRM General.', $employee->getFirstName(), $employee->getLastName()),
                $employeeUser,
                $dayOffset,
            );
            ++$dayOffset;
        }
    }

    private function ensureCalendarEvent(
        ObjectManager $manager,
        Calendar $calendar,
        string $title,
        string $description,
        ?User $user,
        int $dayOffset,
    ): void {
        $existing = $manager->getRepository(Event::class)->findOneBy([
            'calendar' => $calendar,
            'title' => $title,
        ]);
        if ($existing instanceof Event) {
            return;
        }

        $startAt = (new DateTimeImmutable(sprintf('+%d day', $dayOffset)))->setTime(9, 0);
        $event = (new Event())
            ->setCalendar($calendar)
            ->setUser($user)
            ->setTitle($title)
            ->setDescription($description)
            ->setStartAt($startAt)
            ->setEndAt($startAt->modify('+1 hour'))
            ->setStatus(EventStatus::CONFIRMED)
            ->setVisibility(EventVisibility::PRIVATE)
            ->setLocation('CRM General HQ')
            ->setTimezone('Europe/Paris')
            ->setOrganizerName('CRM General')
            ->setOrganizerEmail('crm-general@example.test');

        $manager->persist($event);
    }

    private function isBlank(string $value): bool
    {
        return trim($value) === '';
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
        $isBroWorld = $this->isBroWorldProject($project);
        $broWorldAssignees = $isBroWorld ? $this->getCanonicalUsers() : [];
        $broWorldBlueprints = $isBroWorld ? (self::BRO_WORLD_TASKS_BY_SPRINT[$sprint->getName()] ?? []) : [];
        $effectiveCount = $broWorldBlueprints !== [] ? count($broWorldBlueprints) : $count;
        $sprintStartDate = $sprint->getStartDate();
        $sprintEndDate = $sprint->getEndDate();

        for ($index = 0; $index < $effectiveCount; $index++) {
            $taskStatus = $faker->randomElement(TaskStatus::cases());
            $taskPriority = $faker->randomElement(TaskPriority::cases());
            $taskTitle = $faker->sentence(5);
            $taskDescription = $faker->paragraph(2);
            $dueAt = DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-1 week', '+2 months'));
            $estimatedHours = (float)$faker->randomFloat(1, 2, 30);
            if ($isBroWorld) {
                /** @var array{
                 *     title: non-empty-string,
                 *     description: non-empty-string,
                 *     status: TaskStatus,
                 *     priority: TaskPriority,
                 *     estimatedHours: float
                 * }|null $taskBlueprint
                 */
                $taskBlueprint = $broWorldBlueprints[$index] ?? null;
                if (is_array($taskBlueprint)) {
                    $taskStatus = $taskBlueprint['status'];
                    $taskPriority = $taskBlueprint['priority'];
                    $taskTitle = $taskBlueprint['title'];
                    $taskDescription = $taskBlueprint['description'];
                    $estimatedHours = $taskBlueprint['estimatedHours'];
                }
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
                ->setEstimatedHours($estimatedHours);

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
                    $title = $index === 0
                        ? sprintf('Implementation package - %s', $task->getTitle())
                        : sprintf('QA and acceptance package - %s', $task->getTitle());
                    $description = $index === 0
                        ? 'Backend and frontend implementation ready for reviewer validation.'
                        : 'Validation report including tests, edge cases, and release checklist.';
                    $status = match (true) {
                        $index === 0 && $task->getStatus() === TaskStatus::DONE => TaskRequestStatus::DONE,
                        $index === 0 && $task->getStatus() === TaskStatus::IN_PROGRESS => TaskRequestStatus::PROGRESS,
                        $index === 1 && $task->getStatus() === TaskStatus::DONE => TaskRequestStatus::APPROVED,
                        $index === 1 && $task->getStatus() === TaskStatus::IN_PROGRESS => TaskRequestStatus::PENDING,
                        default => TaskRequestStatus::PENDING,
                    };
                    $taskDueAt = $task->getDueAt();
                    if ($taskDueAt instanceof DateTimeImmutable) {
                        $requestedAt = $taskDueAt->modify(sprintf('-%d days', 5 - min(4, $index)));
                    }
                    $plannedHours = $index === 0 ? 12.0 : 6.0;
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
        $effectiveCount = max(1, min($count, count(self::BRO_WORLD_SPRINTS)));
        for ($index = 0; $index < $effectiveCount; $index++) {
            $sprintBlueprint = self::BRO_WORLD_SPRINTS[$index];
            $startDate = (new DateTimeImmutable($sprintBlueprint['startOffset']))->setTime(0, 0);
            $endDate = $startDate->modify(sprintf('+%d days', $sprintBlueprint['durationDays'] - 1));
            $sprint = (new Sprint())
                ->setProject($project)
                ->setName($sprintBlueprint['name'])
                ->setGoal($sprintBlueprint['goal'])
                ->setStatus($sprintBlueprint['status'])
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
