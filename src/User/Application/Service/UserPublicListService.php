<?php

declare(strict_types=1);

namespace App\User\Application\Service;

use App\General\Application\Service\CacheKeyConventionService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserProfile;
use App\User\Infrastructure\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Throwable;

use function array_filter;
use function array_map;
use function array_values;
use function trim;

readonly class UserPublicListService
{
    private const string ELASTIC_INDEX = 'users';

    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private CacheInterface $cache,
        private ElasticsearchServiceInterface $elasticsearchService,
        private CacheKeyConventionService $cacheKeyConventionService,
    ) {
    }

    /**
     * @param Request $request
     * @return array{users: array<int, array<string, string|null>>, filters: array<string, string>}
     * @throws \JsonException
     * @throws InvalidArgumentException
     */
    public function getList(Request $request): array
    {
        $filters = [
            'q' => trim((string)$request->query->get('q', '')),
        ];

        if ($filters['q'] === '') {
            $users = $this->findUsers(null, null);

            return [
                'users' => $users,
                'filters' => [],
            ];
        }

        $cacheKey = $this->cacheKeyConventionService->buildPublicUserListKey($filters);

        /** @var array<int, array<string, string|null>> $users */
        $users = $this->cache->get($cacheKey, function (ItemInterface $item) use ($filters): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->publicUserListTag());
            }

            $esIds = $filters['q'] !== ''
                ? $this->searchIdsFromElastic($filters['q'])
                : null;

            if ($filters['q'] !== '' && $esIds === []) {
                return [];
            }

            return $this->findUsers($esIds, $filters['q']);
        });

        return [
            'users' => $users,
            'filters' => array_filter($filters, static fn (string $value): bool => $value !== ''),
        ];
    }

    /**
     * @param string $username
     * @return array<string,mixed>
     */
    public function buildMePayload(string $username): array
    {
        $user = $this->userRepository->findOneBy(['username' => $username]);

        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'photo' => $user->getPhoto(),
            'coins' => $user->getCoins()
        ];
    }

    private function ensureProfile(User $user): UserProfile
    {
        $profile = $user->getProfile();

        if ($profile === null) {
            $profile = new UserProfile();
            $profile->setUser($user);
            $user->setProfile($profile);
            $this->entityManager->persist($profile);
            $this->entityManager->flush();
        }

        return $profile;
    }

    /**
     * @param array<int, string>|null $esIds
     *
     * @return array<int, array<string, string|null>>
     */
    private function findUsers(?array $esIds, ?string $query): array
    {
        $qb = $this->userRepository->createQueryBuilder('user')
            ->select('user')
            ->andWhere('user.visible = :visible')
            ->setParameter('visible', true)
            ->orderBy('user.createdAt', 'DESC');

        if ($esIds !== null) {
            $qb->andWhere('user.id IN (:ids)')
                ->setParameter('ids', $esIds);
        }

        if ($query !== null && $esIds === null) {
            $qb
                ->andWhere('LOWER(user.email) LIKE LOWER(:q) OR LOWER(user.firstName) LIKE LOWER(:q) OR LOWER(user.lastName) LIKE LOWER(:q)')
                ->setParameter('q', '%' . $query . '%');
        }

        /** @var array<int, User> $entities */
        $entities = $qb->getQuery()->getResult();

        return array_map(static fn (User $user): array => [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'photo' => $user->getPhoto(),
        ], $entities);
    }

    private function searchIdsFromElastic(string $query): ?array
    {
        try {
            $body = [
                'query' => $query === ''
                    ? [
                        'match_all' => (object)[],
                    ]
                    : [
                        'multi_match' => [
                            'query' => $query,
                            'type' => 'phrase_prefix',
                            'fields' => ['email^3', 'firstName^2', 'lastName^2', 'username'],
                        ],
                    ],
                '_source' => ['id'],
            ];

            $response = $this->elasticsearchService->search(self::ELASTIC_INDEX, $body, 0, 1000);
        } catch (Throwable) {
            return null;
        }

        $hits = $response['hits']['hits'] ?? [];

        return array_values(array_filter(array_map(static fn (array $hit): ?string => $hit['_source']['id'] ?? $hit['_id'] ?? null, $hits)));
    }
}
