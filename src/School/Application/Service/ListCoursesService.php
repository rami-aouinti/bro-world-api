<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\School\Application\Serializer\SchoolViewMapper;
use App\School\Domain\Entity\School;
use App\School\Infrastructure\Repository\CourseRepository;
use Symfony\Component\HttpFoundation\Request;

final readonly class ListCoursesService
{
    public function __construct(
        private CourseRepository $courseRepository,
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

        $qb = $this->courseRepository->createQueryBuilder('course')
            ->innerJoin('course.schoolClass', 'class')
            ->leftJoin('course.teacher', 'teacher')
            ->leftJoin('teacher.user', 'teacherUser')
            ->orderBy('course.createdAt', 'DESC')
            ->setFirstResult($queryOptions->offset())
            ->setMaxResults($queryOptions->limit);

        if ($school !== null) {
            $qb->innerJoin('class.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $school->getId());
        }

        if ($queryOptions->filters['q'] !== '') {
            $qb->andWhere('LOWER(course.name) LIKE LOWER(:q) OR LOWER(teacherUser.firstName) LIKE LOWER(:q) OR LOWER(teacherUser.lastName) LIKE LOWER(:q) OR LOWER(teacherUser.email) LIKE LOWER(:q)')
                ->setParameter('q', '%' . $queryOptions->filters['q'] . '%');
        }

        $items = $this->viewMapper->mapCourseCollection($qb->getQuery()->getResult());

        $countQb = $this->courseRepository->createQueryBuilder('course')
            ->select('COUNT(course.id)')
            ->innerJoin('course.schoolClass', 'class')
            ->leftJoin('course.teacher', 'teacher')
            ->leftJoin('teacher.user', 'teacherUser');

        if ($school !== null) {
            $countQb->innerJoin('class.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $school->getId());
        }

        if ($queryOptions->filters['q'] !== '') {
            $countQb->andWhere('LOWER(course.name) LIKE LOWER(:q) OR LOWER(teacherUser.firstName) LIKE LOWER(:q) OR LOWER(teacherUser.lastName) LIKE LOWER(:q) OR LOWER(teacherUser.email) LIKE LOWER(:q)')
                ->setParameter('q', '%' . $queryOptions->filters['q'] . '%');
        }

        $totalItems = (int)$countQb->getQuery()->getSingleScalarResult();

        return $this->listResponseFactory->create($queryOptions, $totalItems, $items, $school === null ? [] : [
            'schoolId' => $school->getId(),
        ]);
    }
}
