<?php

declare(strict_types=1);

namespace App\Tests\Application\Blog\Transport\Controller\Api\V1;

use App\Tests\TestCase\WebTestCase;
use App\User\Infrastructure\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;

final class BlogControllerTest extends WebTestCase
{
    public function testCreateGeneralBlogAllowsRootUser(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/blogs/general',
            [],
            [],
            $this->getJsonHeaders(),
            json_encode([
                'title' => 'General Blog From Root',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(202);
    }

    public function testCreateGeneralBlogRejectsNonRootUser(): void
    {
        $client = $this->getTestClient('john-user', 'password-user');

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/blogs/general',
            [],
            [],
            $this->getJsonHeaders(),
            json_encode([
                'title' => 'Should Fail',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testApplicationReadReflectsIsAuthorWithAndWithoutAuth(): void
    {
        $anonymousClient = $this->getTestClient();
        $authenticatedClient = $this->getTestClient('john-user', 'password-user');

        $anonymousClient->request(Request::METHOD_GET, self::API_URL_PREFIX . '/v1/blogs/application/shop-ops-center');
        self::assertResponseStatusCodeSame(200);
        /** @var array<string, mixed> $anonymousPayload */
        $anonymousPayload = json_decode((string)$anonymousClient->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $authenticatedClient->request(Request::METHOD_GET, self::API_URL_PREFIX . '/v1/blogs/application/shop-ops-center');
        self::assertResponseStatusCodeSame(200);
        /** @var array<string, mixed> $authenticatedPayload */
        $authenticatedPayload = json_decode((string)$authenticatedClient->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $johnUser = $userRepository->findOneBy(['username' => 'john-user']);
        self::assertNotNull($johnUser);

        self::assertAuthorFlagsForAnonymousPayload($anonymousPayload);
        self::assertAuthorFlagsForAuthenticatedPayload($authenticatedPayload, $johnUser->getId());
    }



    public function testCreateReactionRejectsUnsupportedType(): void
    {
        $client = $this->getTestClient('john-user', 'password-user');

        $client->request(Request::METHOD_GET, self::API_URL_PREFIX . '/v1/blogs/application/shop-ops-center');
        self::assertResponseStatusCodeSame(200);
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $targetCommentId = self::findFirstCommentId($payload);
        self::assertNotNull($targetCommentId);

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/blog/comments/' . $targetCommentId . '/reactions',
            [],
            [],
            $this->getJsonHeaders(),
            json_encode(['type' => 'unsupported'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
    }

    public function testCreateReactionRequiresAuthentication(): void
    {
        $anonymousClient = $this->getTestClient();
        $authenticatedClient = $this->getTestClient('john-user', 'password-user');

        $authenticatedClient->request(Request::METHOD_GET, self::API_URL_PREFIX . '/v1/blogs/application/shop-ops-center');
        self::assertResponseStatusCodeSame(200);
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string)$authenticatedClient->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $targetCommentId = self::findFirstCommentId($payload);
        self::assertNotNull($targetCommentId);

        $anonymousClient->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/blog/comments/' . $targetCommentId . '/reactions',
            [],
            [],
            $this->getJsonHeaders(),
            json_encode(['type' => 'heart'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateReactionUpsertsForSameAuthorAndComment(): void
    {
        $client = $this->getTestClient('john-user', 'password-user');

        $client->request(Request::METHOD_GET, self::API_URL_PREFIX . '/v1/blogs/application/shop-ops-center');
        self::assertResponseStatusCodeSame(200);
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $johnUser = $userRepository->findOneBy(['username' => 'john-user']);
        self::assertNotNull($johnUser);

        $targetCommentId = self::findFirstCommentId($payload);
        self::assertNotNull($targetCommentId);

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/blog/comments/' . $targetCommentId . '/reactions',
            [],
            [],
            $this->getJsonHeaders(),
            json_encode(['type' => 'heart'], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(202);

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/blog/comments/' . $targetCommentId . '/reactions',
            [],
            [],
            $this->getJsonHeaders(),
            json_encode(['type' => 'laugh'], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(202);

        $client->request(Request::METHOD_GET, self::API_URL_PREFIX . '/v1/blogs/application/shop-ops-center');
        self::assertResponseStatusCodeSame(200);
        /** @var array<string, mixed> $updatedPayload */
        $updatedPayload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $targetComment = self::findCommentById($updatedPayload, $targetCommentId);
        self::assertIsArray($targetComment);
        self::assertArrayHasKey('reactions', $targetComment);
        self::assertIsArray($targetComment['reactions']);

        $johnUserReactionTypes = [];
        foreach ($targetComment['reactions'] as $reaction) {
            if (!is_array($reaction)) {
                continue;
            }

            if (($reaction['authorId'] ?? null) === $johnUser->getId()) {
                $johnUserReactionTypes[] = $reaction['type'] ?? null;
            }
        }

        self::assertCount(1, $johnUserReactionTypes);
        self::assertSame('laugh', $johnUserReactionTypes[0]);
    }


    /**
     * @param array<string, mixed> $node
     */
    private static function findFirstCommentId(array $node): ?string
    {
        if (isset($node['id']) && isset($node['content'])) {
            return is_string($node['id']) ? $node['id'] : null;
        }

        foreach (['posts', 'comments', 'children'] as $listKey) {
            if (!isset($node[$listKey]) || !is_array($node[$listKey])) {
                continue;
            }

            foreach ($node[$listKey] as $child) {
                if (!is_array($child)) {
                    continue;
                }

                $id = self::findFirstCommentId($child);
                if (is_string($id)) {
                    return $id;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function findCommentById(array $node, string $commentId): ?array
    {
        if (($node['id'] ?? null) === $commentId && isset($node['content'])) {
            return $node;
        }

        foreach (['posts', 'comments', 'children'] as $listKey) {
            if (!isset($node[$listKey]) || !is_array($node[$listKey])) {
                continue;
            }

            foreach ($node[$listKey] as $child) {
                if (!is_array($child)) {
                    continue;
                }

                $found = self::findCommentById($child, $commentId);
                if (is_array($found)) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function assertAuthorFlagsForAnonymousPayload(array $payload): void
    {
        self::assertAuthorFlags($payload, null);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function assertAuthorFlagsForAuthenticatedPayload(array $payload, string $userId): void
    {
        self::assertAuthorFlags($payload, $userId);
    }

    /**
     * @param array<string, mixed> $node
     */
    private static function assertAuthorFlags(array $node, ?string $userId): void
    {
        if (isset($node['authorId'])) {
            self::assertArrayHasKey('isAuthor', $node);
            self::assertSame($userId !== null && $node['authorId'] === $userId, $node['isAuthor']);
        }

        foreach (['posts', 'comments', 'reactions', 'children'] as $listKey) {
            if (!isset($node[$listKey]) || !is_array($node[$listKey])) {
                continue;
            }

            foreach ($node[$listKey] as $child) {
                if (!is_array($child)) {
                    continue;
                }

                self::assertAuthorFlags($child, $userId);
            }
        }
    }
}
