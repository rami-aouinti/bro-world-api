<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruit\Application\Service;

use App\Recruit\Application\Service\InterviewDecisionService;
use App\Recruit\Domain\Entity\InterviewFeedback;
use App\Recruit\Domain\Enum\InterviewRecommendation;
use PHPUnit\Framework\TestCase;

final class InterviewDecisionServiceTest extends TestCase
{
    public function testBuildSummaryReturnsNullMetricsWhenNoFeedback(): void
    {
        $service = new InterviewDecisionService();

        $summary = $service->buildSummary([]);

        self::assertSame(0, $summary['count']);
        self::assertNull($summary['average']['overall']);
        self::assertNull($summary['finalRecommendation']);
    }

    public function testBuildSummaryComputesAveragesDispersionAndFinalRecommendation(): void
    {
        $service = new InterviewDecisionService();

        $feedbackA = (new InterviewFeedback())
            ->setSkillsScore(4)
            ->setCommunicationScore(5)
            ->setCultureFitScore(4)
            ->setRecommendation(InterviewRecommendation::HIRE);

        $feedbackB = (new InterviewFeedback())
            ->setSkillsScore(2)
            ->setCommunicationScore(3)
            ->setCultureFitScore(2)
            ->setRecommendation(InterviewRecommendation::NO_HIRE);

        $feedbackC = (new InterviewFeedback())
            ->setSkillsScore(5)
            ->setCommunicationScore(4)
            ->setCultureFitScore(5)
            ->setRecommendation(InterviewRecommendation::HIRE);

        $summary = $service->buildSummary([$feedbackA, $feedbackB, $feedbackC]);

        self::assertSame(3, $summary['count']);
        self::assertSame(3.67, $summary['average']['skills']);
        self::assertSame(4.0, $summary['average']['communication']);
        self::assertSame(3.67, $summary['average']['cultureFit']);
        self::assertSame('hire', $summary['finalRecommendation']);
        self::assertSame(2, $summary['recommendations']['hire']);
        self::assertSame(1, $summary['recommendations']['no_hire']);
        self::assertGreaterThan(0.0, $summary['dispersion']['overall']);
    }
}
