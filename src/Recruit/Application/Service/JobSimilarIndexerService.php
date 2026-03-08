<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\Recruit\Domain\Entity\Job;
use Doctrine\ORM\EntityManagerInterface;

use function array_count_values;
use function array_filter;
use function array_keys;
use function array_map;
use function array_slice;
use function array_values;
use function implode;
use function is_string;
use function mb_strtolower;
use function preg_replace;
use function str_contains;
use function trim;

class JobSimilarIndexerService
{
    final public const string INDEX_NAME = 'recruit_job_similar';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ElasticsearchServiceInterface $elasticsearchService,
    ) {
    }

    public function reindexAll(): int
    {
        /** @var list<Job> $jobs */
        $jobs = $this->entityManager->getRepository(Job::class)
            ->createQueryBuilder('job')
            ->leftJoin('job.tags', 'tag')->addSelect('tag')
            ->leftJoin('job.recruit', 'recruit')->addSelect('recruit')
            ->getQuery()
            ->getResult();

        foreach ($jobs as $job) {
            $scores = [];

            foreach ($jobs as $candidate) {
                if ($candidate->getId() === $job->getId()) {
                    continue;
                }

                if ($candidate->getRecruit()?->getId() !== $job->getRecruit()?->getId()) {
                    continue;
                }

                $score = $this->computeScore($job, $candidate);
                if ($score <= 0.0) {
                    continue;
                }

                $scores[] = [
                    'id' => $candidate->getId(),
                    'score' => $score,
                ];
            }

            usort($scores, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

            $similarJobIds = array_values(array_map(
                static fn (array $item): string => (string) $item['id'],
                array_slice($scores, 0, 3),
            ));

            $this->elasticsearchService->index(self::INDEX_NAME, $job->getId(), [
                'jobId' => $job->getId(),
                'similarJobIds' => $similarJobIds,
                'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ]);
        }

        return count($jobs);
    }

    private function computeScore(Job $job, Job $candidate): float
    {
        $score = 0.0;

        $score += 5 * $this->tokenOverlap($job->getTitle(), $candidate->getTitle());
        $score += 3 * $this->tokenOverlap($job->getMissionTitle(), $candidate->getMissionTitle());
        $score += 3 * $this->tokenOverlap($job->getSummary(), $candidate->getSummary());
        $score += 3 * $this->tokenOverlap($job->getMissionDescription(), $candidate->getMissionDescription());

        $jobResponsibilities = $this->tokenize(implode(' ', array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $job->getResponsibilities())));
        $candidateResponsibilities = $this->tokenize(implode(' ', array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $candidate->getResponsibilities())));
        $score += 4 * $this->overlapBetweenTokenLists($jobResponsibilities, $candidateResponsibilities);

        $jobProfile = $this->tokenize(implode(' ', array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $job->getProfile())));
        $candidateProfile = $this->tokenize(implode(' ', array_map(static fn (mixed $value): string => is_string($value) ? $value : '', $candidate->getProfile())));
        $score += 4 * $this->overlapBetweenTokenLists($jobProfile, $candidateProfile);

        $jobTags = array_map(static fn ($tag): string => mb_strtolower($tag->getLabel()), $job->getTags()->toArray());
        $candidateTags = array_map(static fn ($tag): string => mb_strtolower($tag->getLabel()), $candidate->getTags()->toArray());
        $score += 2 * $this->overlapBetweenTokenLists($jobTags, $candidateTags);

        return $score;
    }

    private function tokenOverlap(string $left, string $right): float
    {
        return $this->overlapBetweenTokenLists($this->tokenize($left), $this->tokenize($right));
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private function overlapBetweenTokenLists(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 0.0;
        }

        $leftIndex = array_count_values($left);
        $rightIndex = array_count_values($right);

        $common = 0;
        $total = 0;

        $keys = array_keys($leftIndex + $rightIndex);
        foreach ($keys as $token) {
            $leftCount = (int) ($leftIndex[$token] ?? 0);
            $rightCount = (int) ($rightIndex[$token] ?? 0);
            $common += min($leftCount, $rightCount);
            $total += max($leftCount, $rightCount);
        }

        if ($total === 0) {
            return 0.0;
        }

        return $common / $total;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $normalized = mb_strtolower(trim($text));
        if ($normalized === '') {
            return [];
        }

        $normalized = (string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized);
        $parts = preg_split('/\s+/u', $normalized) ?: [];

        return array_values(array_filter($parts, static fn (mixed $part): bool => is_string($part) && $part !== '' && !str_contains($part, '\n')));
    }
}
