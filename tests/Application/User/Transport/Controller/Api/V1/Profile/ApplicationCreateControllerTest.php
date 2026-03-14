<?php

declare(strict_types=1);

namespace App\Tests\Application\User\Transport\Controller\Api\V1\Profile;

use App\Blog\Infrastructure\Repository\BlogPostRepository;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\Blog\Infrastructure\Repository\BlogTagRepository;
use App\Calendar\Infrastructure\Repository\CalendarRepository;
use App\Calendar\Infrastructure\Repository\EventRepository;
use App\Chat\Infrastructure\Repository\ChatRepository;
use App\Chat\Infrastructure\Repository\ConversationRepository;
use App\Crm\Domain\Entity\Crm;
use App\General\Domain\Utils\JSON;
use App\Platform\Application\Service\ApplicationPluginProvisioningService;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PluginKey;
use App\Quiz\Infrastructure\Repository\QuizQuestionRepository;
use App\Quiz\Infrastructure\Repository\QuizRepository;
use App\Role\Domain\Enum\Role;
use App\Recruit\Domain\Entity\Recruit;
use App\School\Domain\Entity\School;
use App\Shop\Domain\Entity\Shop;
use App\Tests\TestCase\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApplicationCreateControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/profile/applications';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that creating an application with calendar/chat/blog/quiz plugins provisions default entities and idempotent seeds.')]
    public function testThatPostApplicationWithPluginsProvisionDefaultsAndSeedData(): void
    {
        $client = $this->getTestClient('john-user', 'password-user');
        $payload = [
            'platformId' => '40000000-0000-1000-8000-000000000001',
            'title' => 'App with all plugins',
            'description' => 'Provisioning integration test',
            'plugins' => [
                [
                    'pluginId' => '50000000-0000-1000-8000-000000000001',
                ],
                [
                    'pluginId' => '50000000-0000-1000-8000-000000000002',
                ],
                [
                    'pluginId' => '50000000-0000-1000-8000-000000000005',
                ],
                [
                    'pluginId' => '50000000-0000-1000-8000-000000000006',
                ],
            ],
        ];

        $client->request('POST', $this->baseUrl, content: JSON::encode($payload));
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertArrayHasKey('id', $responseData);

        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $application = $entityManager->getRepository(Application::class)->find($responseData['id']);
        self::assertInstanceOf(Application::class, $application);

        $calendarRepository = $container->get(CalendarRepository::class);
        $chatRepository = $container->get(ChatRepository::class);
        $conversationRepository = $container->get(ConversationRepository::class);
        $eventRepository = $container->get(EventRepository::class);
        $blogRepository = $container->get(BlogRepository::class);
        $blogPostRepository = $container->get(BlogPostRepository::class);
        $blogTagRepository = $container->get(BlogTagRepository::class);
        $quizRepository = $container->get(QuizRepository::class);
        $quizQuestionRepository = $container->get(QuizQuestionRepository::class);

        $calendar = $calendarRepository->findOneByApplication($application);
        $chat = $chatRepository->findOneByApplication($application);
        $blog = $blogRepository->findOneByApplication($application);
        $quiz = $quizRepository->findOneByApplication($application);

        self::assertNotNull($calendar);
        self::assertSame('Default calendar', $calendar->getTitle());
        self::assertNotNull($chat);
        self::assertSame($application->getSlug(), $chat->getApplicationSlug());
        self::assertNotNull($blog);
        self::assertNotNull($quiz);

        self::assertCount(1, $conversationRepository->findBy([
            'chat' => $chat,
        ]));
        self::assertCount(1, $eventRepository->findBy([
            'calendar' => $calendar,
            'title' => 'Welcome event',
        ]));
        self::assertCount(1, $blogTagRepository->findBy([
            'blog' => $blog,
            'label' => 'Getting Started',
        ]));
        self::assertCount(1, $blogPostRepository->findBy([
            'blog' => $blog,
            'content' => 'Welcome to your application blog.',
        ]));
        self::assertCount(1, $quizQuestionRepository->findBy([
            'quiz' => $quiz,
            'title' => 'What is the first step to launch this app?',
        ]));

        $provisioningService = $container->get(ApplicationPluginProvisioningService::class);
        $provisioningService->provision($application, [PluginKey::CALENDAR, PluginKey::CHAT, PluginKey::BLOG, PluginKey::QUIZ]);
        $entityManager->flush();

        self::assertCount(1, $calendarRepository->findBy([
            'application' => $application,
        ]));
        self::assertCount(1, $chatRepository->findBy([
            'application' => $application,
        ]));
        self::assertCount(1, $blogRepository->findBy([
            'application' => $application,
        ]));
        self::assertCount(1, $quizRepository->findBy([
            'application' => $application,
        ]));

        self::assertCount(1, $conversationRepository->findBy([
            'chat' => $chat,
        ]));
        self::assertCount(1, $eventRepository->findBy([
            'calendar' => $calendar,
            'title' => 'Welcome event',
        ]));
        self::assertCount(1, $blogTagRepository->findBy([
            'blog' => $blog,
            'label' => 'Getting Started',
        ]));
        self::assertCount(1, $blogPostRepository->findBy([
            'blog' => $blog,
            'content' => 'Welcome to your application blog.',
        ]));
        self::assertCount(1, $quizQuestionRepository->findBy([
            'quiz' => $quiz,
            'title' => 'What is the first step to launch this app?',
        ]));
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that creating applications provisions one entity per platform (crm, shop, school, recruit) and stays idempotent on update.')]
    public function testThatPostApplicationProvisionsPlatformEntityAndIsIdempotentOnUpdate(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $crmApplication = $this->createApplication('40000000-0000-1000-8000-000000000001', 'CRM app auto provision');
        $shopApplication = $this->createApplication('40000000-0000-1000-8000-000000000003', 'Shop app auto provision');
        $schoolApplication = $this->createApplication('40000000-0000-1000-8000-000000000005', 'School app auto provision');
        $recruitApplication = $this->createApplication('40000000-0000-1000-8000-000000000004', 'Recruit app auto provision');

        self::assertCount(1, $entityManager->getRepository(Crm::class)->findBy([
            'application' => $crmApplication,
        ]));

        self::assertContains(Role::CRM_OWNER->value, $crmApplication->getUser()->getRoles());
        self::assertCount(1, $entityManager->getRepository(Shop::class)->findBy([
            'application' => $shopApplication,
        ]));
        self::assertCount(1, $entityManager->getRepository(School::class)->findBy([
            'application' => $schoolApplication,
        ]));
        self::assertCount(1, $entityManager->getRepository(Recruit::class)->findBy([
            'application' => $recruitApplication,
        ]));

        $crmOwnerGroups = array_filter(
            $crmApplication->getUser()->getUserGroups()->toArray(),
            static fn ($userGroup): bool => $userGroup->getRole()->getId() === Role::CRM_OWNER->value,
        );

        self::assertCount(1, $crmOwnerGroups);

        $crmApplication->setDescription('CRM app updated');
        $shopApplication->setDescription('Shop app updated');
        $schoolApplication->setDescription('School app updated');
        $recruitApplication->setDescription('Recruit app updated');
        $entityManager->flush();

        self::assertCount(1, $entityManager->getRepository(Crm::class)->findBy([
            'application' => $crmApplication,
        ]));

        self::assertContains(Role::CRM_OWNER->value, $crmApplication->getUser()->getRoles());
        self::assertCount(1, $entityManager->getRepository(Shop::class)->findBy([
            'application' => $shopApplication,
        ]));
        self::assertCount(1, $entityManager->getRepository(School::class)->findBy([
            'application' => $schoolApplication,
        ]));
        self::assertCount(1, $entityManager->getRepository(Recruit::class)->findBy([
            'application' => $recruitApplication,
        ]));

        $crmOwnerGroups = array_filter(
            $crmApplication->getUser()->getUserGroups()->toArray(),
            static fn ($userGroup): bool => $userGroup->getRole()->getId() === Role::CRM_OWNER->value,
        );

        self::assertCount(1, $crmOwnerGroups);
    }

    /**
     * @throws Throwable
     */
    private function createApplication(string $platformId, string $title): Application
    {
        $client = $this->getTestClient('john-user', 'password-user');

        $client->request('POST', $this->baseUrl, content: JSON::encode([
            'platformId' => $platformId,
            'title' => $title,
            'description' => 'Platform provisioning integration test',
        ]));

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), "Response:\n" . $response);

        $responseData = JSON::decode($content, true);
        self::assertIsArray($responseData);
        self::assertArrayHasKey('id', $responseData);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $application = $entityManager->getRepository(Application::class)->find($responseData['id']);

        self::assertInstanceOf(Application::class, $application);

        return $application;
    }
}
