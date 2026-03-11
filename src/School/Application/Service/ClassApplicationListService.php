<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Service\CacheKeyConventionService;
use App\School\Domain\Entity\School;
use App\School\Domain\Entity\SchoolClass;
use App\School\Infrastructure\Repository\SchoolClassRepository;
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
    ) {
    }

    /** @return array<string,mixed> */
    public function getList(Request $request, string $applicationSlug, School $school): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = ['q' => trim((string)$request->query->get('q', ''))];
        $cacheKey = $this->cacheKeyConventionService->buildSchoolClassApplicationListKey($applicationSlug, $page, $limit, $filters);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug, $school, $filters, $page, $limit): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->schoolClassListByApplicationTag($applicationSlug));
            }

            $qb = $this->classRepository->createQueryBuilder('class')
                ->andWhere('class.school = :school')->setParameter('school', $school)
                ->orderBy('class.createdAt', 'DESC')
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);
            if ($filters['q'] !== '') {
                $qb->andWhere('LOWER(class.name) LIKE LOWER(:q)')->setParameter('q', '%' . $filters['q'] . '%');
            }

            $items = array_map(static fn (SchoolClass $class): array => ['id' => $class->getId(), 'name' => $class->getName()], $qb->getQuery()->getResult());

            $countQb = $this->classRepository->createQueryBuilder('class')->select('COUNT(class.id)')
                ->andWhere('class.school = :school')->setParameter('school', $school);
            if ($filters['q'] !== '') {
                $countQb->andWhere('LOWER(class.name) LIKE LOWER(:q)')->setParameter('q', '%' . $filters['q'] . '%');
            }
            $totalItems = (int)$countQb->getQuery()->getSingleScalarResult();

            return [
                'items' => $items,
                'pagination' => ['page' => $page, 'limit' => $limit, 'totalItems' => $totalItems, 'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0],
                'meta' => ['applicationSlug' => $applicationSlug, 'schoolId' => $school->getId(), 'filters' => array_filter($filters)],
            ];
        });
    }
}
