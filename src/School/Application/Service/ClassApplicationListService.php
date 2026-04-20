<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Service\CacheKeyConventionService;
use App\School\Application\Serializer\SchoolViewMapper;
use App\School\Domain\Entity\School;
use App\School\Infrastructure\Repository\SchoolClassRepository;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class ClassApplicationListService
{
    public function __construct(
        private SchoolClassRepository $classRepository,
        private CacheInterface $cache,
        private CacheKeyConventionService $cacheKeyConventionService,
        private SchoolViewMapper $viewMapper,
        private SchoolListRequestHelper $listRequestHelper,
        private SchoolListResponseFactory $listResponseFactory,
    ) {
    }

    /**
     * @return array<string,mixed>
     * @throws \JsonException
     * @throws InvalidArgumentException
     */
    public function list(Request $request, ?School $school = null): array
    {
        $queryOptions = $this->listRequestHelper->fromRequest($request, ['q']);
        $applicationSlug = (string)$request->attributes->get('applicationSlug', 'general');
        $cacheKey = $this->cacheKeyConventionService->buildSchoolClassApplicationListKey($applicationSlug, $queryOptions->page, $queryOptions->limit, $queryOptions->filters);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug, $school, $queryOptions): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->schoolClassListByApplicationTag($applicationSlug));
            }

            $qb = $this->classRepository->createQueryBuilder('class')
                ->orderBy('class.createdAt', 'DESC')
                ->setFirstResult($queryOptions->offset())
                ->setMaxResults($queryOptions->limit);
            if ($school !== null) {
                $qb->andWhere('class.school = :school')->setParameter('school', $school);
            }
            if ($queryOptions->filters['q'] !== '') {
                $qb->andWhere('LOWER(class.name) LIKE LOWER(:q)')->setParameter('q', '%' . $queryOptions->filters['q'] . '%');
            }

            $items = $this->viewMapper->mapClassCollection($qb->getQuery()->getResult());

            $countQb = $this->classRepository->createQueryBuilder('class')->select('COUNT(class.id)');
            if ($school !== null) {
                $countQb->andWhere('class.school = :school')->setParameter('school', $school);
            }
            if ($queryOptions->filters['q'] !== '') {
                $countQb->andWhere('LOWER(class.name) LIKE LOWER(:q)')->setParameter('q', '%' . $queryOptions->filters['q'] . '%');
            }
            $totalItems = (int)$countQb->getQuery()->getSingleScalarResult();

            $meta = [
                'applicationSlug' => $applicationSlug,
            ];
            if ($school !== null) {
                $meta['schoolId'] = $school->getId();
                $meta['schoolName'] = $school->getName();
            }

            return $this->listResponseFactory->create(
                $queryOptions,
                $totalItems,
                $items,
                $meta,
            );
        });
    }
}
