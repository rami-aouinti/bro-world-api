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
    public function list(Request $request, ?School $school = null): array
    {
        $queryOptions = $this->listRequestHelper->fromRequest($request, ['q']);

        $qb = $this->teacherRepository->createQueryBuilder('teacher')
            ->orderBy('teacher.createdAt', 'DESC')
            ->distinct()
            ->setFirstResult($queryOptions->offset())
            ->setMaxResults($queryOptions->limit);
        if ($school !== null) {
            $qb->innerJoin('teacher.classes', 'class')
                ->innerJoin('class.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $school->getId());
        }
        if ($queryOptions->filters['q'] !== '') {
            $qb->innerJoin('teacher.user', 'teacherUser')
                ->andWhere('LOWER(teacherUser.firstName) LIKE LOWER(:q) OR LOWER(teacherUser.lastName) LIKE LOWER(:q) OR LOWER(teacherUser.email) LIKE LOWER(:q)')
                ->setParameter('q', '%' . $queryOptions->filters['q'] . '%');
        }

        $items = $this->viewMapper->mapTeacherCollection($qb->getQuery()->getResult());

        $countQb = $this->teacherRepository->createQueryBuilder('teacher')->select('COUNT(DISTINCT teacher.id)');
        if ($school !== null) {
            $countQb->innerJoin('teacher.classes', 'class')
                ->innerJoin('class.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $school->getId());
        }
        if ($queryOptions->filters['q'] !== '') {
            $countQb->innerJoin('teacher.user', 'teacherUser')
                ->andWhere('LOWER(teacherUser.firstName) LIKE LOWER(:q) OR LOWER(teacherUser.lastName) LIKE LOWER(:q) OR LOWER(teacherUser.email) LIKE LOWER(:q)')
                ->setParameter('q', '%' . $queryOptions->filters['q'] . '%');
        }
        $totalItems = (int)$countQb->getQuery()->getSingleScalarResult();

        return $this->listResponseFactory->create($queryOptions, $totalItems, $items, $school === null ? [] : [
            'schoolId' => $school->getId(),
        ]);
    }
}
