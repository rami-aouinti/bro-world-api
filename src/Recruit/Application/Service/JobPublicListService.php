<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\Recruit\Domain\Entity\Application as RecruitApplication;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Entity\Resume;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function ceil;
use function count;
use function floor;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function mb_strtolower;
use function md5;
use function max;
use function preg_match;
use function preg_split;
use function round;
use function sprintf;
use function strlen;
use function strtolower;
use function trim;

class JobPublicListService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache,
        private readonly ElasticsearchServiceInterface $elasticsearchService,
    ) {
    }

    /**
     * @param Request $request
     * @param string $applicationSlug
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public function getList(Request $request, string $applicationSlug, ?User $loggedInUser = null): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));

        $filters = [
            'company' => trim((string) $request->query->get('company', '')),
            'salaryMin' => max(0, $request->query->getInt('salaryMin', 0)),
            'salaryMax' => max(0, $request->query->getInt('salaryMax', 0)),
            'contractType' => trim((string) $request->query->get('contractType', '')),
            'workMode' => trim((string) $request->query->get('workMode', '')),
            'schedule' => trim((string) $request->query->get('schedule', '')),
            'postedAtLabel' => trim((string) $request->query->get('postedAtLabel', '')),
            'location' => trim((string) $request->query->get('location', '')),
            'q' => trim((string) $request->query->get('q', '')),
        ];

        $cacheKey = 'recruit_job_public_' . md5((string)json_encode([
                'page' => $page,
                'limit' => $limit,
                'filters' => $filters,
                'applicationSlug' => $applicationSlug,
                'userId' => $loggedInUser?->getId(),
            ], JSON_THROW_ON_ERROR));

        /** @var array<string, mixed> $result */
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($page, $limit, $filters, $applicationSlug, $loggedInUser): array {
            $item->expiresAfter(120);

            $recruit = $this->resolveRecruitByApplicationSlug($applicationSlug);

            $qb = $this->entityManager
                ->getRepository(Job::class)
                ->createQueryBuilder('job')
                ->leftJoin('job.company', 'company')->addSelect('company')
                ->leftJoin('job.salary', 'salary')->addSelect('salary')
                ->leftJoin('job.badges', 'badge')->addSelect('badge')
                ->leftJoin('job.tags', 'tag')->addSelect('tag')
                ->andWhere('job.recruit = :recruit')
                ->setParameter('recruit', $recruit->getId() , UuidBinaryOrderedTimeType::NAME)
                ->orderBy('job.createdAt', 'DESC')
                ->addOrderBy('job.id', 'DESC');

            if ($filters['company'] !== '') {
                $qb->andWhere('LOWER(company.name) LIKE :company')
                    ->setParameter('company', '%' . mb_strtolower($filters['company']) . '%');
            }

            if ($filters['contractType'] !== '') {
                $qb->andWhere('job.contractType = :contractType')
                    ->setParameter('contractType', $filters['contractType']);
            }

            if ($filters['workMode'] !== '') {
                $qb->andWhere('job.workMode = :workMode')
                    ->setParameter('workMode', $filters['workMode']);
            }

            if ($filters['schedule'] !== '') {
                $qb->andWhere('job.schedule = :schedule')
                    ->setParameter('schedule', $filters['schedule']);
            }

            if ($filters['location'] !== '') {
                $qb->andWhere('LOWER(job.location) LIKE :location')
                    ->setParameter('location', '%' . mb_strtolower($filters['location']) . '%');
            }

            if ($filters['salaryMin'] > 0) {
                $qb->andWhere('salary.max >= :salaryMin')
                    ->setParameter('salaryMin', $filters['salaryMin']);
            }

            if ($filters['salaryMax'] > 0) {
                $qb->andWhere('salary.min <= :salaryMax')
                    ->setParameter('salaryMax', $filters['salaryMax']);
            }

            $this->applyPostedAtLabelFilter($qb, $filters['postedAtLabel']);

            $esIds = $this->searchIdsFromElastic($filters);
            if ($esIds !== null) {
                if ($esIds === []) {
                    return [
                        'jobs' => [],
                        'pagination' => [
                            'page' => $page,
                            'limit' => $limit,
                            'totalItems' => 0,
                            'totalPages' => 0,
                        ],
                    ];
                }

                $qb->andWhere('job.id IN (:esIds)')
                    ->setParameter('esIds', $esIds);
            }

            $userSkillKeywords = $loggedInUser instanceof User
                ? $this->getUserResumeSkillKeywords($loggedInUser)
                : [];

            $query = $qb
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
                ->getQuery();

            $paginator = new Paginator($query, true);
            $totalItems = $paginator->count();

            $jobs = [];
            $jobIds = [];
            /** @var Job $job */
            foreach ($paginator as $job) {
                $jobIds[] = $job->getId();
                $ownerId = $job->getOwner()?->getId();
                $jobPayload = [
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
                    'postedAtLabel' => $this->buildPostedAtLabel($job->getCreatedAt()),
                    'summary' => $job->getSummary(),
                    'matchScore' => $userSkillKeywords === []
                        ? $job->getMatchScore()
                        : $this->computeMatchScore($job, $userSkillKeywords),
                    'badges' => array_map(static fn ($badge): string => $badge->getLabel(), $job->getBadges()->toArray()),
                    'tags' => array_map(static fn ($tag): string => $tag->getLabel(), $job->getTags()->toArray()),
                    'missionTitle' => $job->getMissionTitle(),
                    'missionDescription' => $job->getMissionDescription(),
                    'responsibilities' => $job->getResponsibilities(),
                    'profile' => $job->getProfile(),
                    'benefits' => $job->getBenefits(),
                ];

                if ($loggedInUser instanceof User) {
                    $jobPayload['owner'] = $ownerId !== null && $ownerId === $loggedInUser->getId();
                }

                $jobs[] = $jobPayload;
            }

            if ($loggedInUser instanceof User && $jobs !== []) {
                $appliedJobIds = $this->getAppliedJobIds($loggedInUser, $jobIds);

                $jobs = array_map(static function (array $job) use ($appliedJobIds): array {
                    $isOwner = (bool) ($job['owner'] ?? false);
                    $jobId = $job['id'] ?? '';

                    $job['owner'] = $isOwner;
                    $job['apply'] = in_array($jobId, $appliedJobIds, true);

                    return $job;
                }, $jobs);
            }

            return [
                'jobs' => $jobs,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'totalItems' => $totalItems,
                    'totalPages' => $totalItems > 0 ? (int) ceil($totalItems / $limit) : 0,
                ],
            ];
        });

        $result['filters'] = array_filter($filters, static fn (string|int $value): bool => $value !== '' && $value !== 0);

        return $result;
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

    /**
     * @param array<string, string|int> $filters
     *
     * @return array<int, string>|null
     */
    private function searchIdsFromElastic(array $filters): ?array
    {
        if ($filters['q'] === '' && $filters['company'] === '' && $filters['location'] === '') {
            return null;
        }

        try {
            $must = [];

            if ($filters['q'] !== '') {
                $must[] = [
                    'multi_match' => [
                        'query' => (string) $filters['q'],
                        'type' => 'phrase_prefix',
                        'fields' => [
                            'title^4',
                            'summary^3',
                            'missionDescription^2',
                            'responsibilities',
                            'profile',
                            'benefits',
                            'tags',
                        ],
                    ],
                ];
            }

            if ($filters['company'] !== '') {
                $must[] = ['match_phrase_prefix' => ['companyName' => (string) $filters['company']]];
            }

            if ($filters['location'] !== '') {
                $must[] = ['match_phrase_prefix' => ['location' => (string) $filters['location']]];
            }

            $response = $this->elasticsearchService->search(
                ElasticsearchServiceInterface::INDEX_PREFIX . '_*',
                [
                    'query' => [
                        'bool' => [
                            'must' => $must,
                        ],
                    ],
                    '_source' => ['id'],
                ],
                0,
                2000,
            );

            if (!is_array($response) || !isset($response['hits']['hits']) || !is_array($response['hits']['hits'])) {
                return null;
            }

            $ids = [];
            foreach ($response['hits']['hits'] as $hit) {
                if (is_array($hit) && isset($hit['_source']['id']) && is_string($hit['_source']['id'])) {
                    $id = $hit['_source']['id'];

                    if ($id !== '') {
                        $ids[] = $id;
                    }
                }
            }

            return array_values(array_unique($ids));
        } catch (Throwable) {
            return null;
        }
    }

    private function applyPostedAtLabelFilter(QueryBuilder $qb, string $postedAtLabel): void
    {
        if ($postedAtLabel === '') {
            return;
        }

        $now = new DateTimeImmutable();
        $label = strtolower($postedAtLabel);

        if ($label === 'vor kurzem') {
            $from = $now->modify('-1 day');
            $qb->andWhere('job.createdAt >= :postedAtFrom')
                ->setParameter('postedAtFrom', $from);

            return;
        }

        if (preg_match('/vor\s+(\d+)\s+tag(?:e)?/i', $postedAtLabel, $matches) === 1) {
            $days = (int) ($matches[1] ?? 0);
            if ($days > 0) {
                $from = $now->modify(sprintf('-%d day', $days));
                $to = $now->modify(sprintf('-%d day', $days - 1));
                $qb->andWhere('job.createdAt >= :postedAtFrom')
                    ->andWhere('job.createdAt < :postedAtTo')
                    ->setParameter('postedAtFrom', $from)
                    ->setParameter('postedAtTo', $to);
            }

            return;
        }

        if (preg_match('/vor\s+(\d+)\s+woche(?:n)?/i', $postedAtLabel, $matches) === 1) {
            $weeks = (int) ($matches[1] ?? 0);
            if ($weeks > 0) {
                $from = $now->modify(sprintf('-%d week', $weeks));
                $to = $now->modify(sprintf('-%d week', $weeks - 1));
                $qb->andWhere('job.createdAt >= :postedAtFrom')
                    ->andWhere('job.createdAt < :postedAtTo')
                    ->setParameter('postedAtFrom', $from)
                    ->setParameter('postedAtTo', $to);
            }

            return;
        }

        if (preg_match('/vor\s+(\d+)\s+monat(?:e)?/i', $postedAtLabel, $matches) === 1) {
            $months = (int) ($matches[1] ?? 0);
            if ($months > 0) {
                $from = $now->modify(sprintf('-%d month', $months));
                $to = $now->modify(sprintf('-%d month', $months - 1));
                $qb->andWhere('job.createdAt >= :postedAtFrom')
                    ->andWhere('job.createdAt < :postedAtTo')
                    ->setParameter('postedAtFrom', $from)
                    ->setParameter('postedAtTo', $to);
            }

            return;
        }

        if (preg_match('/vor\s+(\d+)\s+jahr(?:e|en)?/i', $postedAtLabel, $matches) === 1) {
            $years = (int) ($matches[1] ?? 0);
            if ($years > 0) {
                $from = $now->modify(sprintf('-%d year', $years));
                $to = $now->modify(sprintf('-%d year', $years - 1));
                $qb->andWhere('job.createdAt >= :postedAtFrom')
                    ->andWhere('job.createdAt < :postedAtTo')
                    ->setParameter('postedAtFrom', $from)
                    ->setParameter('postedAtTo', $to);
            }
        }
    }

    /**
     * @param list<string> $jobIds
     *
     * @return list<string>
     */
    private function getAppliedJobIds(User $loggedInUser, array $jobIds): array
    {
        if ($jobIds === []) {
            return [];
        }

        /** @var list<RecruitApplication> $applications */
        $applications = $this->entityManager
            ->getRepository(RecruitApplication::class)
            ->createQueryBuilder('application')
            ->innerJoin('application.job', 'job')
            ->innerJoin('application.applicant', 'applicant')
            ->innerJoin('applicant.user', 'user')
            ->andWhere('user = :user')
            ->andWhere('job.id IN (:jobIds)')
            ->setParameter('user', $loggedInUser->getId(), UuidBinaryOrderedTimeType::NAME)
            ->setParameter('jobIds', $jobIds)
            ->getQuery()
            ->getResult();

        /** @var list<string> $appliedJobIds */
        $appliedJobIds = array_values(array_unique(array_filter(array_map(
            static fn (RecruitApplication $application): string => $application->getJob()->getId(),
            $applications,
        ), static fn (mixed $id): bool => is_string($id) && $id !== '')));

        return $appliedJobIds;
    }

    /**
     * @return list<string>
     */
    private function getUserResumeSkillKeywords(User $loggedInUser): array
    {
        $resume = $this->entityManager
            ->getRepository(Resume::class)
            ->createQueryBuilder('resume')
            ->leftJoin('resume.skills', 'skill')->addSelect('skill')
            ->andWhere('resume.owner = :owner')
            ->setParameter('owner', $loggedInUser->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('resume.createdAt', 'DESC')
            ->addOrderBy('resume.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$resume instanceof Resume) {
            return [];
        }

        $keywords = [];
        foreach ($resume->getSkills() as $skill) {
            $title = trim(mb_strtolower($skill->getTitle()));
            if ($title === '') {
                continue;
            }

            $keywords[] = $title;

            $parts = preg_split('/[^\p{L}\p{N}]+/u', $title);
            if (!is_array($parts)) {
                continue;
            }

            foreach ($parts as $part) {
                $word = trim($part);
                if ($word !== '' && strlen($word) >= 3) {
                    $keywords[] = $word;
                }
            }
        }

        /** @var list<string> $uniqueKeywords */
        $uniqueKeywords = array_values(array_unique(array_filter($keywords, static fn (string $value): bool => $value !== '')));

        return $uniqueKeywords;
    }

    /**
     * @param list<string> $userSkillKeywords
     */
    private function computeMatchScore(Job $job, array $userSkillKeywords): int
    {
        if ($userSkillKeywords === []) {
            return $job->getMatchScore();
        }

        $jobCorpusParts = [
            mb_strtolower($job->getTitle()),
            mb_strtolower($job->getSummary()),
            mb_strtolower($job->getMissionDescription()),
            mb_strtolower(implode(' ', $job->getProfile())),
            mb_strtolower(implode(' ', $job->getResponsibilities())),
        ];

        foreach ($job->getTags() as $tag) {
            $jobCorpusParts[] = mb_strtolower($tag->getLabel());
        }

        $jobCorpus = ' ' . implode(' ', $jobCorpusParts) . ' ';

        $matchedSkills = 0;
        foreach ($userSkillKeywords as $keyword) {
            if ($keyword !== '' && str_contains($jobCorpus, ' ' . $keyword . ' ')) {
                ++$matchedSkills;
            }
        }

        return (int) max(0, min(100, round(($matchedSkills / count($userSkillKeywords)) * 100)));
    }

    private function resolveRecruitByApplicationSlug(string $applicationSlug): Recruit
    {
        $recruit = $this->entityManager
            ->getRepository(Recruit::class)
            ->createQueryBuilder('recruit')
            ->innerJoin('recruit.application', 'application')
            ->addSelect('application')
            ->where('application.slug = :applicationSlug')
            ->setParameter('applicationSlug', $applicationSlug)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$recruit instanceof Recruit) {
            throw new NotFoundHttpException('Application not found.');
        }

        return $recruit;
    }

}
