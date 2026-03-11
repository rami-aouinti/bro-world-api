<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

use function array_map;
use function count;
use function in_array;
use function is_array;
use function is_string;

class JobPublicDetailService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ElasticsearchServiceInterface $elasticsearchService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetail(string $applicationSlug, string $jobSlug): array
    {
        $recruit = $this->resolveRecruitByApplicationSlug($applicationSlug);

        $job = $this->entityManager->getRepository(Job::class)->findOneBy([
            'recruit' => $recruit,
            'slug' => $jobSlug,
        ]);

        if (!$job instanceof Job) {
            throw new NotFoundHttpException('Job not found.');
        }

        $similarJobIds = $this->getSimilarJobIds($job->getId());
        $similarJobs = $this->getSimilarJobs($recruit, $similarJobIds);

        return [
            'job' => $this->buildJobPayload($job),
            'similarJobs' => $similarJobs,
        ];
    }

    /**
     * @return list<string>
     */
    private function getSimilarJobIds(string $jobId): array
    {
        try {
            $response = $this->elasticsearchService->search(
                JobSimilarIndexerService::INDEX_NAME,
                [
                    'query' => [
                        'ids' => [
                            'values' => [$jobId],
                        ],
                    ],
                    '_source' => ['similarJobIds'],
                ],
                0,
                1,
            );

            if (!is_array($response) || !is_array($response['hits']['hits'] ?? null) || ($response['hits']['hits'] ?? []) === []) {
                return [];
            }

            $first = $response['hits']['hits'][0] ?? null;
            if (!is_array($first) || !is_array($first['_source']['similarJobIds'] ?? null)) {
                return [];
            }

            /** @var list<string> $ids */
            $ids = array_values(array_filter($first['_source']['similarJobIds'], static fn (mixed $id): bool => is_string($id) && $id !== ''));

            return $ids;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param list<string> $similarJobIds
     * @return list<array<string, mixed>>
     */
    private function getSimilarJobs(Recruit $recruit, array $similarJobIds): array
    {
        if ($similarJobIds === []) {
            return [];
        }

        /** @var list<Job> $jobs */
        $jobs = $this->entityManager->getRepository(Job::class)
            ->createQueryBuilder('job')
            ->leftJoin('job.company', 'company')->addSelect('company')
            ->leftJoin('job.salary', 'salary')->addSelect('salary')
            ->leftJoin('job.badges', 'badge')->addSelect('badge')
            ->leftJoin('job.tags', 'tag')->addSelect('tag')
            ->andWhere('job.recruit = :recruit')
            ->andWhere('job.isPublished = :isPublished')
            ->andWhere('job.id IN (:ids)')
            ->setParameter('recruit', $recruit)
            ->setParameter('ids', $similarJobIds)
            ->getQuery()
            ->getResult();

        usort($jobs, static function (Job $left, Job $right) use ($similarJobIds): int {
            $leftIndex = array_search($left->getId(), $similarJobIds, true);
            $rightIndex = array_search($right->getId(), $similarJobIds, true);

            return ($leftIndex === false ? count($similarJobIds) : $leftIndex) <=> ($rightIndex === false ? count($similarJobIds) : $rightIndex);
        });

        return array_map(fn (Job $item): array => $this->buildJobPayload($item), array_values(array_filter($jobs, static fn (Job $item): bool => in_array($item->getId(), $similarJobIds, true))));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildJobPayload(Job $job): array
    {
        return [
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
            'experienceLevel' => $job->getExperienceLevelValue(),
            'yearsExperienceMin' => $job->getYearsExperienceMin(),
            'yearsExperienceMax' => $job->getYearsExperienceMax(),
            'salary' => [
                'min' => $job->getSalary()?->getMin() ?? 0,
                'max' => $job->getSalary()?->getMax() ?? 0,
                'currency' => $job->getSalary()?->getCurrency() ?? 'EUR',
                'period' => $job->getSalary()?->getPeriod() ?? 'year',
            ],
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
