<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Interview;
use App\Recruit\Domain\Entity\InterviewFeedback;
use App\Recruit\Domain\Enum\InterviewRecommendation;
use App\Recruit\Infrastructure\Repository\ApplicationRepository;
use App\Recruit\Infrastructure\Repository\InterviewFeedbackRepository;
use App\Recruit\Infrastructure\Repository\InterviewRepository;
use App\User\Domain\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function in_array;
use function is_int;
use function is_string;
use function mb_strlen;
use function trim;

readonly class InterviewFeedbackService
{
    public function __construct(
        private InterviewRepository $interviewRepository,
        private InterviewFeedbackRepository $feedbackRepository,
        private ApplicationRepository $applicationRepository,
        private InterviewDecisionService $decisionService,
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function upsert(string $interviewId, array $payload, User $loggedInUser): InterviewFeedback
    {
        $interview = $this->interviewRepository->find($interviewId);
        if (!$interview instanceof Interview) {
            throw new NotFoundHttpException('Interview not found.');
        }

        $this->assertCanWriteFeedback($interview, $loggedInUser);

        $feedback = $this->feedbackRepository->findOneByInterviewAndInterviewer($interview, $loggedInUser);
        if (!$feedback instanceof InterviewFeedback) {
            $feedback = (new InterviewFeedback())
                ->setInterview($interview)
                ->setInterviewer($loggedInUser);
        }

        $feedback
            ->setSkillsScore($this->extractScore($payload, 'skills'))
            ->setCommunicationScore($this->extractScore($payload, 'communication'))
            ->setCultureFitScore($this->extractScore($payload, 'cultureFit'))
            ->setRecommendation($this->extractRecommendation($payload))
            ->setComment($this->extractComment($payload));

        $this->feedbackRepository->save($feedback);

        return $feedback;
    }

    /**
     * @return array<string,mixed>
     */
    public function getApplicationSummary(string $applicationId, User $loggedInUser): array
    {
        $application = $this->applicationRepository->find($applicationId);
        if (!$application instanceof Application) {
            throw new NotFoundHttpException('Application not found.');
        }

        $this->assertCanReadSummary($application, $loggedInUser);

        $feedbacks = [];
        foreach ($application->getInterviews() as $interview) {
            foreach ($this->feedbackRepository->findByInterview($interview) as $feedback) {
                $feedbacks[] = $feedback;
            }
        }

        return [
            'applicationId' => $application->getId(),
            'summary' => $this->decisionService->buildSummary($feedbacks),
        ];
    }

    private function assertCanWriteFeedback(Interview $interview, User $loggedInUser): void
    {
        if (!in_array($loggedInUser->getId(), $interview->getInterviewerIds(), true)) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only assigned interviewers can submit feedback for this interview.');
        }
    }

    private function assertCanReadSummary(Application $application, User $loggedInUser): void
    {
        $isHiringManager = $application->getJob()->getOwner()?->getId() === $loggedInUser->getId();
        if ($isHiringManager) {
            return;
        }

        foreach ($application->getInterviews() as $interview) {
            if (in_array($loggedInUser->getId(), $interview->getInterviewerIds(), true)) {
                return;
            }
        }

        throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only assigned interviewers or the hiring manager can access this summary.');
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function extractScore(array $payload, string $field): int
    {
        if (!is_int($payload[$field] ?? null)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "' . $field . '" must be an integer between 1 and 5.');
        }

        $score = $payload[$field];
        if ($score < 1 || $score > 5) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "' . $field . '" must be an integer between 1 and 5.');
        }

        return $score;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function extractRecommendation(array $payload): InterviewRecommendation
    {
        if (!is_string($payload['recommendation'] ?? null)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "recommendation" must be one of: hire, no_hire.');
        }

        $recommendation = InterviewRecommendation::tryFrom($payload['recommendation']);
        if ($recommendation === null) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "recommendation" must be one of: hire, no_hire.');
        }

        return $recommendation;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function extractComment(array $payload): ?string
    {
        if (!isset($payload['comment'])) {
            return null;
        }

        if (!is_string($payload['comment'])) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "comment" must be a string or null.');
        }

        $comment = trim($payload['comment']);
        if ($comment === '') {
            return null;
        }

        if (mb_strlen($comment) > 5000) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "comment" is too long.');
        }

        return $comment;
    }
}
