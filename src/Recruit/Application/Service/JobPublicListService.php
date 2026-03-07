<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Job;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\Request;

class JobPublicListService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /** @return array<string, mixed> */
    public function getList(Request $request): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));

        $filters = [
            'company' => trim((string) $request->query->get('company', '')),
            'salaryMin' => $request->query->getInt('salaryMin', 0),
            'salaryMax' => $request->query->getInt('salaryMax', 0),
            'contractType' => trim((string) $request->query->get('contractType', '')),
            'workMode' => trim((string) $request->query->get('workMode', '')),
            'schedule' => trim((string) $request->query->get('schedule', '')),
            'postedAtLabel' => trim((string) $request->query->get('postedAtLabel', '')),
            'location' => trim((string) $request->query->get('location', '')),
        ];

        $qb = $this->entityManager->getRepository(Job::class)
            ->createQueryBuilder('job')
            ->leftJoin('job.company', 'company')->addSelect('company')
            ->leftJoin('job.salary', 'salary')->addSelect('salary')
            ->leftJoin('job.badges', 'badge')->addSelect('badge')
            ->leftJoin('job.tags', 'tag')->addSelect('tag')
            ->orderBy('job.createdAt', 'DESC');

        if ($filters['company'] !== '') {
            $qb->andWhere('LOWER(company.name) LIKE :company')->setParameter('company', '%' . mb_strtolower($filters['company']) . '%');
        }
        if ($filters['contractType'] !== '') {
            $qb->andWhere('job.contractType = :contractType')->setParameter('contractType', $filters['contractType']);
        }
        if ($filters['workMode'] !== '') {
            $qb->andWhere('job.workMode = :workMode')->setParameter('workMode', $filters['workMode']);
        }
        if ($filters['schedule'] !== '') {
            $qb->andWhere('job.schedule = :schedule')->setParameter('schedule', $filters['schedule']);
        }
        if ($filters['location'] !== '') {
            $qb->andWhere('LOWER(job.location) LIKE :location')->setParameter('location', '%' . mb_strtolower($filters['location']) . '%');
        }
        if ($filters['salaryMin'] > 0) {
            $qb->andWhere('salary.max >= :salaryMin')->setParameter('salaryMin', $filters['salaryMin']);
        }
        if ($filters['salaryMax'] > 0) {
            $qb->andWhere('salary.min <= :salaryMax')->setParameter('salaryMax', $filters['salaryMax']);
        }

        $query = $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery();
        $paginator = new Paginator($query, true);

        $items = [];
        foreach ($paginator as $job) {
            if (!$job instanceof Job) {
                continue;
            }

            $postedAtLabel = $this->buildPostedAtLabel($job->getCreatedAt());
            if ($filters['postedAtLabel'] !== '' && mb_strtolower($filters['postedAtLabel']) !== mb_strtolower($postedAtLabel)) {
                continue;
            }

            $items[] = [
                'id' => $job->getId(),
                'slug' => $job->getSlug(),
                'title' => $job->getTitle(),
                'company' => [
                    'name' => $job->getCompany()?->getName() ?? '',
                    'logo' => $job->getCompany()?->getLogo() ?? '',
                    'sector' => $job->getCompany()?->getSector() ?? '',
                    'size' => $job->getCompany()?->getSize() ?? '',
                ],
                'location' => $job->getLocation(),
                'contractType' => $job->getContractTypeValue(),
                'workMode' => $job->getWorkModeValue(),
                'schedule' => $job->getScheduleValue(),
                'salary' => [
                    'min' => $job->getSalary()?->getMin() ?? 0,
                    'max' => $job->getSalary()?->getMax() ?? 0,
                    'currency' => $job->getSalary()?->getCurrency() ?? 'EUR',
                    'period' => $job->getSalary()?->getPeriod() ?? 'year',
                ],
                'postedAtLabel' => $postedAtLabel,
                'summary' => $job->getSummary(),
                'matchScore' => $job->getMatchScore(),
                'badges' => array_map(static fn ($badge): string => $badge->getLabel(), $job->getBadges()->toArray()),
                'tags' => array_map(static fn ($tag): string => $tag->getLabel(), $job->getTags()->toArray()),
                'missionTitle' => $job->getMissionTitle(),
                'missionDescription' => $job->getMissionDescription(),
                'responsibilities' => $job->getResponsibilities(),
                'profile' => $job->getProfile(),
                'benefits' => $job->getBenefits(),
            ];
        }

        $totalItems = $paginator->count();

        return [
            'jobs' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'totalItems' => $totalItems,
                'totalPages' => $totalItems > 0 ? (int) ceil($totalItems / $limit) : 0,
            ],
            'filters' => array_filter($filters, static fn (string|int $value): bool => $value !== '' && $value !== 0),
        ];
    }

    private function buildPostedAtLabel(?DateTimeImmutable $createdAt): string
    {
        if ($createdAt === null) {
            return 'vor kurzem';
        }

        $now = new DateTimeImmutable();
        $diff = $createdAt->diff($now);

        if ($diff->y > 0) {
            return 'vor ' . $diff->y . ' Jahr' . ($diff->y > 1 ? 'en' : '');
        }
        if ($diff->m > 0) {
            return 'vor ' . $diff->m . ' Monat' . ($diff->m > 1 ? 'en' : '');
        }
        if ($diff->days !== false && $diff->days >= 7) {
            $weeks = (int) floor($diff->days / 7);
            return 'vor ' . $weeks . ' Woche' . ($weeks > 1 ? 'n' : '');
        }
        if ($diff->d > 0) {
            return 'vor ' . $diff->d . ' Tag' . ($diff->d > 1 ? 'en' : '');
        }

        return 'vor kurzem';
    }
}
