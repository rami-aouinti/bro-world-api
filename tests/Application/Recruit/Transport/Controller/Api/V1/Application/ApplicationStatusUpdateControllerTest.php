<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1\Application;

use App\Chat\Domain\Entity\Chat;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\General\Domain\Utils\JSON;
use App\Platform\Domain\Entity\Application as PlatformApplication;
use App\Recruit\Domain\Entity\Application as RecruitApplication;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Tests\TestCase\WebTestCase;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApplicationStatusUpdateControllerTest extends WebTestCase
{
    /**
     * @throws Throwable
     */
    #[TestDox('Test that transition to SCREENING creates conversation and participants for owner and applicant.')]
    public function testThatTransitionToScreeningCreatesConversation(): void
    {
        [$recruitApplication, $platformApplication] = $this->prepareApplicationForScreeningTransition();

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('PATCH', self::API_URL_PREFIX . '/v1/recruit/applications/recruit-talent-core/private/applications/' . $recruitApplication->getId() . '/status', content: JSON::encode([
            'status' => ApplicationStatus::SCREENING->value,
        ]));

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $chat = $entityManager->getRepository(Chat::class)->findOneBy([
            'application' => $platformApplication,
        ]);
        self::assertInstanceOf(Chat::class, $chat);

        $conversation = $entityManager->getRepository(Conversation::class)->findOneBy([
            'chat' => $chat,
        ]);
        self::assertInstanceOf(Conversation::class, $conversation);

        $participants = $entityManager->getRepository(ConversationParticipant::class)->findBy([
            'conversation' => $conversation,
        ]);
        self::assertCount(2, $participants);

        $participantUserIds = array_map(static fn (ConversationParticipant $participant): string => $participant->getUser()->getId(), $participants);
        self::assertContains($recruitApplication->getJob()->getOwner()?->getId(), $participantUserIds);
        self::assertContains($recruitApplication->getApplicant()->getUser()->getId(), $participantUserIds);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that repeated transition to SCREENING is idempotent and does not duplicate conversation or participants.')]
    public function testThatSecondTransitionToScreeningDoesNotDuplicateConversation(): void
    {
        [$recruitApplication, $platformApplication] = $this->prepareApplicationForScreeningTransition();

        $client = $this->getTestClient('john-root', 'password-root');
        $url = self::API_URL_PREFIX . '/v1/recruit/applications/recruit-talent-core/private/applications/' . $recruitApplication->getId() . '/status';

        $client->request('PATCH', $url, content: JSON::encode([
            'status' => ApplicationStatus::SCREENING->value,
        ]));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('PATCH', $url, content: JSON::encode([
            'status' => ApplicationStatus::SCREENING->value,
        ]));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $chat = $entityManager->getRepository(Chat::class)->findOneBy([
            'application' => $platformApplication,
        ]);
        self::assertInstanceOf(Chat::class, $chat);

        $conversations = $entityManager->getRepository(Conversation::class)->findBy([
            'chat' => $chat,
        ]);
        self::assertCount(1, $conversations);

        $participants = $entityManager->getRepository(ConversationParticipant::class)->findBy([
            'conversation' => $conversations[0],
        ]);
        self::assertCount(2, $participants);
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Status transition endpoint is forbidden for ROLE_USER.')]
    public function testThatStatusTransitionEndpointIsForbiddenForRegularRole(): void
    {
        [$recruitApplication] = $this->prepareApplicationForScreeningTransition();

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request('PATCH', self::API_URL_PREFIX . '/v1/recruit/applications/recruit-talent-core/private/applications/' . $recruitApplication->getId() . '/status', content: JSON::encode([
            'status' => ApplicationStatus::SCREENING->value,
        ]));

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that GET status-history returns status transitions with author and comment.')]
    public function testThatStatusHistoryEndpointReturnsTransitionEvents(): void
    {
        [$recruitApplication] = $this->prepareApplicationForScreeningTransition();

        $client = $this->getTestClient('john-root', 'password-root');
        $baseUrl = self::API_URL_PREFIX . '/v1/recruit/applications/recruit-talent-core/private/applications/' . $recruitApplication->getId();

        $client->request('PATCH', $baseUrl . '/status', content: JSON::encode([
            'status' => ApplicationStatus::SCREENING->value,
            'comment' => 'Premier tri RH',
        ]));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('GET', $baseUrl . '/status-history');
        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        /** @var array<int, array<string, mixed>> $payload */
        $payload = JSON::decode($response->getContent() ?: '[]');

        self::assertNotEmpty($payload);
        $lastEvent = $payload[array_key_last($payload)];

        self::assertSame(ApplicationStatus::WAITING->value, $lastEvent['fromStatus']);
        self::assertSame(ApplicationStatus::SCREENING->value, $lastEvent['toStatus']);
        self::assertSame('Premier tri RH', $lastEvent['comment']);
        self::assertNotEmpty($lastEvent['authorId']);
        self::assertNotEmpty($lastEvent['createdAt']);
    }

    /**
     * @return array{0: RecruitApplication, 1: PlatformApplication}
     */
    private function prepareApplicationForScreeningTransition(): array
    {
        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $owner = $entityManager->getRepository(User::class)->findOneBy([
            'username' => 'john-root',
        ]);
        self::assertInstanceOf(User::class, $owner);

        $recruitApplication = $entityManager->getRepository(RecruitApplication::class)->findOneBy([
            'status' => ApplicationStatus::WAITING,
        ]);
        self::assertInstanceOf(RecruitApplication::class, $recruitApplication);
        self::assertSame($owner->getId(), $recruitApplication->getJob()->getOwner()?->getId());

        $platformApplication = $recruitApplication->getJob()->getRecruit()?->getApplication();
        self::assertInstanceOf(PlatformApplication::class, $platformApplication);

        $chat = $entityManager->getRepository(Chat::class)->findOneBy([
            'application' => $platformApplication,
        ]);

        if (!$chat instanceof Chat) {
            $chat = (new Chat())
                ->setApplication($platformApplication)
                ->setApplicationSlug($platformApplication->getSlug());
            $entityManager->persist($chat);
            $entityManager->flush();
        }

        $conversation = $entityManager->getRepository(Conversation::class)->findOneBy([
            'chat' => $chat,
        ]);

        if ($conversation instanceof Conversation) {
            $participants = $entityManager->getRepository(ConversationParticipant::class)->findBy([
                'conversation' => $conversation,
            ]);

            foreach ($participants as $participant) {
                $entityManager->remove($participant);
            }

            $entityManager->remove($conversation);
            $entityManager->flush();
        }

        $recruitApplication->setStatus(ApplicationStatus::WAITING);
        $entityManager->flush();

        return [$recruitApplication, $platformApplication];
    }
}
