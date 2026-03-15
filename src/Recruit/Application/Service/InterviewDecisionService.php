<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\InterviewFeedback;
use App\Recruit\Domain\Enum\InterviewRecommendation;

use function abs;
use function array_filter;
use function array_map;
use function array_sum;
use function count;
use function round;

readonly class InterviewDecisionService
{
    /**
     * @param array<int, InterviewFeedback> $feedbacks
     * @return array<string,mixed>
     */
    public function buildSummary(array $feedbacks): array
    {
        if ($feedbacks === []) {
            return [
                'count' => 0,
                'average' => [
                    'skills' => null,
                    'communication' => null,
                    'cultureFit' => null,
                    'overall' => null,
                ],
                'dispersion' => [
                    'skills' => null,
                    'communication' => null,
                    'cultureFit' => null,
                    'overall' => null,
                ],
                'recommendations' => [
                    InterviewRecommendation::HIRE->value => 0,
                    InterviewRecommendation::NO_HIRE->value => 0,
                ],
                'finalRecommendation' => null,
            ];
        }

        $skills = array_map(static fn (InterviewFeedback $feedback): int => $feedback->getSkillsScore(), $feedbacks);
        $communication = array_map(static fn (InterviewFeedback $feedback): int => $feedback->getCommunicationScore(), $feedbacks);
        $cultureFit = array_map(static fn (InterviewFeedback $feedback): int => $feedback->getCultureFitScore(), $feedbacks);
        $overall = array_map(static fn (InterviewFeedback $feedback): float => ($feedback->getSkillsScore() + $feedback->getCommunicationScore() + $feedback->getCultureFitScore()) / 3, $feedbacks);

        $hireCount = count(array_filter($feedbacks, static fn (InterviewFeedback $feedback): bool => $feedback->getRecommendation() === InterviewRecommendation::HIRE));
        $noHireCount = count($feedbacks) - $hireCount;

        return [
            'count' => count($feedbacks),
            'average' => [
                'skills' => $this->mean($skills),
                'communication' => $this->mean($communication),
                'cultureFit' => $this->mean($cultureFit),
                'overall' => $this->mean($overall),
            ],
            'dispersion' => [
                'skills' => $this->meanAbsoluteDeviation($skills),
                'communication' => $this->meanAbsoluteDeviation($communication),
                'cultureFit' => $this->meanAbsoluteDeviation($cultureFit),
                'overall' => $this->meanAbsoluteDeviation($overall),
            ],
            'recommendations' => [
                InterviewRecommendation::HIRE->value => $hireCount,
                InterviewRecommendation::NO_HIRE->value => $noHireCount,
            ],
            'finalRecommendation' => $hireCount >= $noHireCount
                ? InterviewRecommendation::HIRE->value
                : InterviewRecommendation::NO_HIRE->value,
        ];
    }

    /**
     * @param array<int, int|float> $values
     */
    private function mean(array $values): float
    {
        return round(array_sum($values) / count($values), 2);
    }

    /**
     * @param array<int, int|float> $values
     */
    private function meanAbsoluteDeviation(array $values): float
    {
        $mean = $this->mean($values);
        $deviations = array_map(static fn (int|float $value): float => abs($value - $mean), $values);

        return round(array_sum($deviations) / count($deviations), 2);
    }
}
