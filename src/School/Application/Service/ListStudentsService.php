<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\School\Application\Serializer\SchoolViewMapper;
use App\School\Domain\Entity\School;
use App\School\Infrastructure\Repository\StudentRepository;
use Symfony\Component\HttpFoundation\Request;

final readonly class ListStudentsService
{
    public function __construct(
        private StudentRepository $studentRepository,
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

        $qb = $this->studentRepository->createQueryBuilder('student')
            ->innerJoin('student.schoolClass', 'class')
            ->innerJoin('class.school', 'school')
            ->andWhere('school.id = :schoolId')
            ->setParameter('schoolId', $school->getId())
            ->orderBy('student.createdAt', 'DESC')
            ->setFirstResult($queryOptions->offset())
            ->setMaxResults($queryOptions->limit);
        if ($queryOptions->filters['q'] !== '') {
            $qb->andWhere('LOWER(student.name) LIKE LOWER(:q)')->setParameter('q', '%' . $queryOptions->filters['q'] . '%');
        }

        $items = $this->viewMapper->mapStudentCollection($qb->getQuery()->getResult());

        $countQb = $this->studentRepository->createQueryBuilder('student')->select('COUNT(student.id)')
            ->innerJoin('student.schoolClass', 'class')
            ->innerJoin('class.school', 'school')
            ->andWhere('school.id = :schoolId')
            ->setParameter('schoolId', $school->getId());
        if ($queryOptions->filters['q'] !== '') {
            $countQb->andWhere('LOWER(student.name) LIKE LOWER(:q)')->setParameter('q', '%' . $queryOptions->filters['q'] . '%');
        }
        $totalItems = (int)$countQb->getQuery()->getSingleScalarResult();

        return $this->listResponseFactory->create($queryOptions, $totalItems, $items, [
            'schoolId' => $school->getId(),
        ]);
    }
}
