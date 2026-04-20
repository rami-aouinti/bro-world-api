<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\School\Application\Serializer\SchoolViewMapper;
use App\School\Domain\Entity\School;
use App\School\Infrastructure\Repository\GradeRepository;
use Symfony\Component\HttpFoundation\Request;

final readonly class ListGradesService
{
    public function __construct(
        private GradeRepository $gradeRepository,
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

        $qb = $this->gradeRepository->createQueryBuilder('grade')
            ->innerJoin('grade.exam', 'exam')
            ->innerJoin('grade.student', 'student')
            ->innerJoin('student.user', 'studentUser')
            ->orderBy('grade.createdAt', 'DESC')
            ->setFirstResult($queryOptions->offset())
            ->setMaxResults($queryOptions->limit);
        if ($school !== null) {
            $qb->innerJoin('exam.schoolClass', 'class')
                ->innerJoin('class.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $school->getId());
        }
        if ($queryOptions->filters['q'] !== '') {
            $qb->andWhere('LOWER(exam.title) LIKE LOWER(:q) OR LOWER(studentUser.firstName) LIKE LOWER(:q) OR LOWER(studentUser.lastName) LIKE LOWER(:q)')->setParameter('q', '%' . $queryOptions->filters['q'] . '%');
        }

        $items = $this->viewMapper->mapGradeCollection($qb->getQuery()->getResult());

        $countQb = $this->gradeRepository->createQueryBuilder('grade')->select('COUNT(grade.id)')
            ->innerJoin('grade.exam', 'exam')
            ->innerJoin('grade.student', 'student')
            ->innerJoin('student.user', 'studentUser');
        if ($school !== null) {
            $countQb->innerJoin('exam.schoolClass', 'class')
                ->innerJoin('class.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $school->getId());
        }
        if ($queryOptions->filters['q'] !== '') {
            $countQb->andWhere('LOWER(exam.title) LIKE LOWER(:q) OR LOWER(studentUser.firstName) LIKE LOWER(:q) OR LOWER(studentUser.lastName) LIKE LOWER(:q)')->setParameter('q', '%' . $queryOptions->filters['q'] . '%');
        }
        $totalItems = (int)$countQb->getQuery()->getSingleScalarResult();

        return $this->listResponseFactory->create($queryOptions, $totalItems, $items, $school === null ? [] : [
            'schoolId' => $school->getId(),
        ]);
    }
}
