<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\School\Application\Serializer\SchoolViewMapper;
use App\School\Domain\Entity\School;
use App\School\Infrastructure\Repository\TeacherRepository;
use Symfony\Component\HttpFoundation\Request;

final readonly class ListTeachersService
{
    public function __construct(
        private TeacherRepository $teacherRepository,
        private SchoolViewMapper $viewMapper,
        private SchoolListRequestHelper $listRequestHelper,
        private SchoolListResponseFactory $listResponseFactory,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function list(Request $request, School $school): array
    {
        $queryOptions = $this->listRequestHelper->fromRequest($request, ['q']);

        $qb = $this->teacherRepository->createQueryBuilder('teacher')
            ->innerJoin('teacher.classes', 'class')
            ->innerJoin('class.school', 'school')
            ->andWhere('school.id = :schoolId')
            ->setParameter('schoolId', $school->getId())
            ->orderBy('teacher.createdAt', 'DESC')
            ->distinct()
            ->setFirstResult($queryOptions->offset())
            ->setMaxResults($queryOptions->limit);
        if ($queryOptions->filters['q'] !== '') {
            $qb->andWhere('LOWER(teacher.name) LIKE LOWER(:q)')->setParameter('q', '%' . $queryOptions->filters['q'] . '%');
        }

        $items = $this->viewMapper->mapTeacherCollection($qb->getQuery()->getResult());

        $countQb = $this->teacherRepository->createQueryBuilder('teacher')->select('COUNT(DISTINCT teacher.id)')
            ->innerJoin('teacher.classes', 'class')
            ->innerJoin('class.school', 'school')
            ->andWhere('school.id = :schoolId')
            ->setParameter('schoolId', $school->getId());
        if ($queryOptions->filters['q'] !== '') {
            $countQb->andWhere('LOWER(teacher.name) LIKE LOWER(:q)')->setParameter('q', '%' . $queryOptions->filters['q'] . '%');
        }
        $totalItems = (int)$countQb->getQuery()->getSingleScalarResult();

        return $this->listResponseFactory->create($queryOptions, $totalItems, $items, [
            'schoolId' => $school->getId(),
        ]);
    }
}
