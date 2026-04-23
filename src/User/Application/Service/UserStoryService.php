<?php

declare(strict_types=1);

namespace App\User\Application\Service;

use App\General\Application\Service\CacheInvalidationService;
use App\General\Application\Service\CacheKeyConventionService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserFriendRelation;
use App\User\Domain\Entity\UserStory;
use App\User\Domain\Enum\FriendStatus;
use App\User\Infrastructure\Repository\UserStoryRepository;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Throwable;

use function array_filter;
use function array_flip;
use function array_map;
use function array_values;
use function count;
use function method_exists;
use function usort;

readonly class UserStoryService
{
    private const string ELASTIC_INDEX = 'user_stories';

    public function __construct(
        private UserStoryRepository $userStoryRepository,
        private EntityManagerInterface $entityManager,
        private CacheInterface $cache,
        private CacheKeyConventionService $cacheKeyConventionService,
        private CacheInvalidationService $cacheInvalidationService,
        private ElasticsearchServiceInterface $elasticsearchService,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getActiveStories(User $loggedInUser, int $limit): array
    {
        $visibleUsers = $this->findVisibleUsers($loggedInUser);
        $cacheKey = $this->cacheKeyConventionService->buildPrivateStoryListKey($loggedInUser->getId(), $limit);

        /** @var array<int, array<string, mixed>> $stories */
        $stories = $this->cache->get($cacheKey, function (ItemInterface $item) use ($loggedInUser, $visibleUsers, $limit): array {
            $item->expiresAfter(60);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->tagPrivateStoryList());
            }

            return $this->getActiveStoriesFromSources($loggedInUser, $visibleUsers, $limit);
        });

        return $stories;
    }

    /**
     * @return array<string, mixed>
     */
    public function createStory(User $user, string $imageUrl): array
    {
        if ($imageUrl === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "imageUrl" is required.');
        }

        $story = new UserStory();
        $story
            ->setUser($user)
            ->setImageUrl($imageUrl)
            ->setExpiresAt((new DateTimeImmutable())->add(new DateInterval('PT24H')));

        $this->userStoryRepository->save($story);
        $this->indexStory($story);
        $this->cacheInvalidationService->invalidateUserStoryCaches();

        return $this->mapStory($story);
    }

    public function deleteStory(User $user, string $storyId): void
    {
        $story = $this->userStoryRepository->find($storyId);
        if (!$story instanceof UserStory) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Story not found.');
        }

        if ($story->getUser()->getId() !== $user->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You can delete only your own stories.');
        }

        $this->userStoryRepository->delete($story);

        try {
            $this->elasticsearchService->delete(self::ELASTIC_INDEX, $storyId);
        } catch (Throwable) {
        }

        $this->cacheInvalidationService->invalidateUserStoryCaches();
    }

    /**
     * @param array<int, User> $visibleUsers
     *
     * @return array<int, array<string, mixed>>
     */
    private function getActiveStoriesFromSources(User $loggedInUser, array $visibleUsers, int $limit): array
    {
        $visibleUserIds = array_map(static fn (User $user): string => $user->getId(), $visibleUsers);
        $esIds = $this->searchActiveStoryIdsFromElastic($visibleUserIds, $limit);

        // If Elasticsearch does not have the index/documents yet, fall back to DB query.
        return $this->findActiveStories($loggedInUser, $visibleUsers, $limit, $esIds === [] ? null : $esIds);
    }

    /**
     * @param array<int, User> $visibleUsers
     * @param array<int, string>|null $esIds
     *
     * @return array<int, array<string, mixed>>
     */
    private function findActiveStories(User $loggedInUser, array $visibleUsers, int $limit, ?array $esIds): array
    {
        $since = new DateTimeImmutable('-24 hours');

        $qb = $this->userStoryRepository->createQueryBuilder('story')
            ->select('story', 'user')
            ->innerJoin('story.user', 'user')
            ->andWhere('story.createdAt >= :since')
            ->andWhere('story.expiresAt >= :now')
            ->andWhere('story.user IN (:visibleUsers)')
            ->setParameter('since', $since)
            ->setParameter('now', new DateTimeImmutable())
            ->setParameter('visibleUsers', $visibleUsers)
            ->orderBy('story.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($esIds !== null) {
            $qb->andWhere('story.id IN (:ids)')
                ->setParameter('ids', $esIds);
        }

        /** @var array<int, UserStory> $stories */
        $stories = $qb->getQuery()->getResult();

        if ($esIds !== null && count($stories) > 1) {
            $order = array_flip($esIds);
            usort($stories, static fn (UserStory $a, UserStory $b): int => ($order[$a->getId()] ?? PHP_INT_MAX) <=> ($order[$b->getId()] ?? PHP_INT_MAX));
        }

        $groupedStories = [];
        foreach ($stories as $story) {
            $user = $story->getUser();
            $userId = $user->getId();

            if (!isset($groupedStories[$userId])) {
                $groupedStories[$userId] = [
                    'owner' => $userId === $loggedInUser->getId(),
                    'user' => [
                        'id' => $userId,
                        'username' => $user->getUsername(),
                        'photo' => $user->getPhoto(),
                    ],
                    'stories' => [],
                ];
            }

            $groupedStories[$userId]['stories'][] = $this->mapStory($story);
        }

        $ordered = [];
        if (isset($groupedStories[$loggedInUser->getId()])) {
            $ordered[] = $groupedStories[$loggedInUser->getId()];
            unset($groupedStories[$loggedInUser->getId()]);
        }

        return array_values([...$ordered, ...$groupedStories]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapStory(UserStory $story): array
    {
        return [
            'id' => $story->getId(),
            'imageUrl' => $story->getImageUrl(),
            'createdAt' => $story->getCreatedAt()?->format(DateTimeInterface::ATOM),
            'expiresAt' => $story->getExpiresAt()->format(DateTimeInterface::ATOM),
        ];
    }

    private function indexStory(UserStory $story): void
    {
        try {
            $this->elasticsearchService->index(self::ELASTIC_INDEX, $story->getId(), [
                'id' => $story->getId(),
                'userId' => $story->getUser()->getId(),
                'imageUrl' => $story->getImageUrl(),
                'createdAt' => $story->getCreatedAt()?->format(DateTimeInterface::ATOM),
                'expiresAt' => $story->getExpiresAt()->format(DateTimeInterface::ATOM),
            ]);
        } catch (Throwable) {
        }
    }

    /**
     * @param array<int, string> $visibleUserIds
     * @return array<int, string>|null
     */
    private function searchActiveStoryIdsFromElastic(array $visibleUserIds, int $limit): ?array
    {
        try {
            $response = $this->elasticsearchService->search(self::ELASTIC_INDEX, [
                'query' => [
                    'bool' => [
                        'filter' => [
                            [
                                'range' => [
                                    'expiresAt' => [
                                        'gte' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                                    ],
                                ],
                            ],
                            [
                                'terms' => [
                                    'userId' => $visibleUserIds,
                                ],
                            ],
                        ],
                    ],
                ],
                'sort' => [
                    [
                        'createdAt' => [
                            'order' => 'desc',
                        ],
                    ],
                ],
                '_source' => ['id'],
            ], 0, $limit);
        } catch (Throwable) {
            return null;
        }

        $hits = $response['hits']['hits'] ?? [];

        return array_values(array_filter(array_map(static fn (array $hit): ?string => $hit['_source']['id'] ?? $hit['_id'] ?? null, $hits)));
    }

    /**
     * @return array<int, User>
     */
    private function findVisibleUsers(User $loggedInUser): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('relation, requester, addressee')
            ->from(UserFriendRelation::class, 'relation')
            ->join('relation.requester', 'requester')
            ->join('relation.addressee', 'addressee')
            ->where('(relation.requester = :me OR relation.addressee = :me)')
            ->andWhere('relation.status = :status')
            ->setParameter('me', $loggedInUser)
            ->setParameter('status', FriendStatus::ACCEPTED->value);

        /** @var array<int, UserFriendRelation> $relations */
        $relations = $qb->getQuery()->getResult();

        $users = [
            $loggedInUser->getId() => $loggedInUser,
        ];
        foreach ($relations as $relation) {
            $friend = $relation->getRequester()->getId() === $loggedInUser->getId()
                ? $relation->getAddressee()
                : $relation->getRequester();

            $users[$friend->getId()] = $friend;
        }

        return array_values($users);
    }
}
