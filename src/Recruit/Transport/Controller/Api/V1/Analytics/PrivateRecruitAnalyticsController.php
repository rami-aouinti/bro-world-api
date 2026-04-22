<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Analytics;

use App\Recruit\Application\Service\RecruitAnalyticsService;
use App\Recruit\Application\Service\RecruitResolverService;
use App\Recruit\Infrastructure\Repository\JobRepository;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function sprintf;
use function strtolower;

#[AsController]
#[OA\Tag(name: 'Recruit Analytics')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class PrivateRecruitAnalyticsController
{
    public function __construct(
        private RecruitResolverService $recruitResolverService,
        private RecruitAnalyticsService $recruitAnalyticsService,
        private JobRepository $jobRepository,
    ) {
    }

    #[Route(path: '/v1/recruit/private/analytics', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time'))]
    #[OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date-time'))]
    #[OA\Parameter(name: 'jobId', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'format', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['json', 'csv']))]
    public function __invoke(Request $request, User $loggedInUser): Response
    {
        $recruit = $this->recruitResolverService->resolveFromRequest($request);
        $applicationSlug = (string) $request->attributes->get('applicationSlug', '');

        if ($recruit->getApplication()?->getUser()?->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot access analytics for this application.');
        }

        $from = $this->parseDateFilter($request->query->getString('from', ''));
        $to = $this->parseDateFilter($request->query->getString('to', ''));

        $job = null;
        $jobId = $request->query->getString('jobId', '');
        if ($jobId !== '') {
            $job = $this->jobRepository->find($jobId);
            if ($job === null) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown "jobId" filter.');
            }

            if ($job->getRecruit()?->getId() !== $recruit->getId()) {
                throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'The filtered job does not belong to this application.');
            }
        }

        $analytics = $this->recruitAnalyticsService->getAnalytics($recruit, $from, $to, $job);

        $format = strtolower($request->query->getString('format', 'json'));
        if ($format === 'csv') {
            $content = $this->recruitAnalyticsService->toCsv($analytics);

            return new Response(
                content: $content,
                status: JsonResponse::HTTP_OK,
                headers: [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => HeaderUtils::makeDisposition(
                        HeaderUtils::DISPOSITION_ATTACHMENT,
                        sprintf('recruit-analytics-%s.csv', $applicationSlug),
                    ),
                ],
            );
        }

        return new JsonResponse($analytics);
    }

    private function parseDateFilter(string $raw): ?\DateTimeImmutable
    {
        if ($raw === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Throwable) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Date filters "from" and "to" must be valid ISO-8601 date-time values.');
        }
    }
}
