<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Service\CacheKeyConventionService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\School\Application\Projection\SchoolExamProjection;
use App\School\Application\Serializer\SchoolViewMapper;
use App\School\Infrastructure\Repository\ExamRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Throwable;

readonly class ExamListService
{
    public function __construct(
        private ExamRepository $examRepository,
        private CacheInterface $cache,
        private ElasticsearchServiceInterface $elasticsearchService,
        private CacheKeyConventionService $cacheKeyConventionService,
        private SchoolViewMapper $viewMapper,
        private SchoolListRequestHelper $listRequestHelper,
        private SchoolListResponseFactory $listResponseFactory,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function list(Request $request, string $schoolId): array
    {
        $queryOptions = $this->listRequestHelper->fromRequest($request, ['q', 'title']);
        $applicationSlug = (string)$request->attributes->get('applicationSlug', 'default');
        $cacheKey = $this->cacheKeyConventionService->buildSchoolExamListKey($applicationSlug, $queryOptions->page, $queryOptions->limit, [
            ...$queryOptions->filters,
            'schoolId' => $schoolId,
        ]);

        /** @var array<string,mixed> $result */
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($queryOptions, $schoolId, $applicationSlug): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag([
                    $this->cacheKeyConventionService->schoolExamListTag(),
                    $this->cacheKeyConventionService->schoolExamListTagByApplication($applicationSlug),
                ]);
            }

            $esIds = $this->searchIdsFromElastic($queryOptions->filters);
            if ($esIds === []) {
                return $this->listResponseFactory->create($queryOptions, 0, []);
            }

            $qb = $this->examRepository->createQueryBuilder('exam')->leftJoin('exam.schoolClass', 'class')->leftJoin('exam.teacher', 'teacher')
                ->innerJoin('class.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $schoolId)
                ->setFirstResult($queryOptions->offset())->setMaxResults($queryOptions->limit)->orderBy('exam.createdAt', 'DESC');
            if ($queryOptions->filters['title'] !== '') {
                $qb->andWhere('LOWER(exam.title) LIKE LOWER(:title)')->setParameter('title', '%' . $queryOptions->filters['title'] . '%');
            }
            if ($esIds !== null) {
                $qb->andWhere('exam.id IN (:ids)')->setParameter('ids', $esIds);
            }

            $items = $this->viewMapper->mapExamCollection($qb->getQuery()->getResult());

            $countQb = $this->examRepository->createQueryBuilder('exam')->select('COUNT(exam.id)')
                ->innerJoin('exam.schoolClass', 'class')
                ->innerJoin('class.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $schoolId);
            if ($queryOptions->filters['title'] !== '') {
                $countQb->andWhere('LOWER(exam.title) LIKE LOWER(:title)')->setParameter('title', '%' . $queryOptions->filters['title'] . '%');
            }
            if ($esIds !== null) {
                $countQb->andWhere('exam.id IN (:ids)')->setParameter('ids', $esIds);
            }

            $totalItems = (int)$countQb->getQuery()->getSingleScalarResult();

            return $this->listResponseFactory->create(
                $queryOptions,
                $totalItems,
                $items,
                [
                    'module' => 'school',
                ],
            );
        });

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
