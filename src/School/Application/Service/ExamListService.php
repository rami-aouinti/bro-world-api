<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Service\CacheKeyConventionService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\School\Application\Projection\SchoolExamProjection;
use App\School\Infrastructure\Repository\ExamRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Throwable;
use App\School\Application\Serializer\SchoolApiResponseSerializer;
use App\School\Application\Serializer\SchoolViewMapper;

readonly class ExamListService
{
    public function __construct(
        private ExamRepository $examRepository,
        private CacheInterface $cache,
        private ElasticsearchServiceInterface $elasticsearchService,
        private CacheKeyConventionService $cacheKeyConventionService,
        private SchoolViewMapper $viewMapper,
        private SchoolApiResponseSerializer $responseSerializer,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function getList(Request $request): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'q' => trim((string)$request->query->get('q', '')),
            'title' => trim((string)$request->query->get('title', '')),
        ];
        $cacheKey = $this->cacheKeyConventionService->buildSchoolExamListKey($page, $limit, $filters);

        /** @var array<string,mixed> $result */
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($filters, $page, $limit): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->schoolExamListTag());
            }

            $esIds = $this->searchIdsFromElastic($filters);
            if ($esIds === []) {
                return $this->responseSerializer->list([], [
                    'page' => $page,
                    'limit' => $limit,
                    'totalItems' => 0,
                    'totalPages' => 0,
                ]);
            }

            $qb = $this->examRepository->createQueryBuilder('exam')->leftJoin('exam.schoolClass', 'class')->leftJoin('exam.teacher', 'teacher')
                ->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->orderBy('exam.createdAt', 'DESC');
            if ($filters['title'] !== '') {
                $qb->andWhere('LOWER(exam.title) LIKE LOWER(:title)')->setParameter('title', '%' . $filters['title'] . '%');
            }
            if ($esIds !== null) {
                $qb->andWhere('exam.id IN (:ids)')->setParameter('ids', $esIds);
            }

            $items = $this->viewMapper->mapExamCollection($qb->getQuery()->getResult());

            $countQb = $this->examRepository->createQueryBuilder('exam')->select('COUNT(exam.id)');
            if ($filters['title'] !== '') {
                $countQb->andWhere('LOWER(exam.title) LIKE LOWER(:title)')->setParameter('title', '%' . $filters['title'] . '%');
            }
            if ($esIds !== null) {
                $countQb->andWhere('exam.id IN (:ids)')->setParameter('ids', $esIds);
            }

            $totalItems = (int)$countQb->getQuery()->getSingleScalarResult();

            return $this->responseSerializer->list(
                $items,
                [
                    'page' => $page,
                    'limit' => $limit,
                    'totalItems' => $totalItems,
                    'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0,
                ],
                ['module' => 'school'],
            );
        });

        $result['meta']['filters'] = array_filter($filters, static fn (string $value): bool => $value !== '');

        return $result;
    }

    /** @param array<string,string> $filters
     * @return array<int,string>|null
     */
    private function searchIdsFromElastic(array $filters): ?array
    {
        if ($filters['q'] === '') {
            return null;
        }

        try {
            $response = $this->elasticsearchService->search(SchoolExamProjection::INDEX_NAME, [
                'query' => [
                    'multi_match' => [
                        'query' => $filters['q'],
                        'type' => 'phrase_prefix',
                        'fields' => ['title^3', 'className^2', 'teacherName', 'grades'],
                    ],
                ],
                '_source' => ['id'],
            ], 0, 200);
        } catch (Throwable) {
            return null;
        }

        $hits = $response['hits']['hits'] ?? [];

        return array_values(array_filter(array_map(static fn (array $hit): ?string => $hit['_source']['id'] ?? $hit['_id'] ?? null, $hits)));
    }
}
