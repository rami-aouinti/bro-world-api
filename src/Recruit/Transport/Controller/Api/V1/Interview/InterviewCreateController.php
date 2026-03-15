<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Interview;

use App\Recruit\Application\Security\RecruitPermissions;
use App\Recruit\Application\Service\InterviewService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Interview')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[IsGranted(RecruitPermissions::INTERVIEW_MANAGE)]
readonly class InterviewCreateController
{
    public function __construct(
        private InterviewService $interviewService
    ) {
    }

    #[Route(path: '/v1/recruit/private/applications/{applicationId}/interviews', methods: [Request::METHOD_POST])]
    #[OA\Post(
        summary: 'Crée un entretien pour une candidature.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['scheduledAt', 'durationMinutes', 'mode'],
                properties: [
                    new OA\Property(property: 'scheduledAt', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'durationMinutes', type: 'integer', minimum: 1),
                    new OA\Property(property: 'mode', type: 'string', enum: ['visio', 'phone', 'onsite']),
                    new OA\Property(property: 'locationOrUrl', type: 'string', nullable: true),
                    new OA\Property(property: 'interviewerIds', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Entretien créé.'),
            new OA\Response(response: 400, description: 'Payload invalide ou statut incompatible.'),
            new OA\Response(response: 403, description: 'Accès interdit.'),
        ],
    )]
    #[OA\Parameter(name: 'applicationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    public function __invoke(string $applicationId, Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string,mixed> $payload */
        $payload = $request->toArray();
        $interview = $this->interviewService->create($applicationId, $payload, $loggedInUser);

        return new JsonResponse($this->normalize($interview), JsonResponse::HTTP_CREATED);
    }

    /**
     * @return array<string,mixed>
     */
    private function normalize(\App\Recruit\Domain\Entity\Interview $interview): array
    {
        return [
            'id' => $interview->getId(),
            'applicationId' => $interview->getApplication()->getId(),
            'scheduledAt' => $interview->getScheduledAt()->format(DATE_ATOM),
            'durationMinutes' => $interview->getDurationMinutes(),
            'mode' => $interview->getMode()->value,
            'locationOrUrl' => $interview->getLocationOrUrl(),
            'interviewerIds' => $interview->getInterviewerIds(),
            'status' => $interview->getStatus()->value,
            'notes' => $interview->getNotes(),
        ];
    }
}
