<?php

declare(strict_types=1);

namespace App\Tests\Application\Chat\Transport\Controller\Api\V1\Reaction;

use App\General\Domain\Utils\JSON;
use App\Recruit\Infrastructure\DataFixtures\ORM\LoadRecruitChatCalendarScenarioData;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class UserReactionMutationControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/chat/private';

    /**
     * @throws Throwable
     */
    #[TestDox('Reaction create/patch/delete nominal + validation + ownership authorization')]
    public function testReactionEndpoints(): void
    {
        $messageId = LoadRecruitChatCalendarScenarioData::getUuidByKey('message-john-root-scenario-from-john-root');

        $anonymous = $this->getTestClient();
        $anonymous->request('POST', $this->baseUrl . '/messages/' . $messageId . '/reactions', [], [], [], JSON::encode([
            'reaction' => 'like',
        ]));
        self::assertSame(Response::HTTP_UNAUTHORIZED, $anonymous->getResponse()->getStatusCode());

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('POST', $this->baseUrl . '/messages/' . $messageId . '/reactions', [], [], [], JSON::encode([
            'reaction' => 'like',
        ]));
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $content = $client->getResponse()->getContent();
        self::assertNotFalse($content);
        $payload = JSON::decode($content, true);
        $reactionId = $payload['id'] ?? null;
        self::assertIsString($reactionId);

        $client->request('POST', $this->baseUrl . '/messages/' . $messageId . '/reactions', [], [], [], JSON::encode([
            'reaction' => 'like',
        ]));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $duplicateContent = $client->getResponse()->getContent();
        self::assertNotFalse($duplicateContent);
        $duplicatePayload = JSON::decode($duplicateContent, true);
        self::assertSame($reactionId, $duplicatePayload['id'] ?? null);

        $client->request('PATCH', $this->baseUrl . '/reactions/' . $reactionId, [], [], [], JSON::encode([
            'reaction' => 'love',
        ]));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('PATCH', $this->baseUrl . '/reactions/' . $reactionId, [], [], [], JSON::encode([
            'reaction' => 'invalid-value',
        ]));
        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $otherClient = $this->getTestClient('john-admin', 'password-admin');
        $otherClient->request('PATCH', $this->baseUrl . '/reactions/' . $reactionId, [], [], [], JSON::encode([
            'reaction' => 'wow',
        ]));
        self::assertSame(Response::HTTP_NOT_FOUND, $otherClient->getResponse()->getStatusCode());

        $client->request('DELETE', $this->baseUrl . '/reactions/' . $reactionId);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }
}
