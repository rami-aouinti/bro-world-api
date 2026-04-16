<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\General;

use App\Recruit\Application\Service\GeneralApplicantService;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit General Applicant')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class CreateGeneralApplicantController
{
    public function __construct(
        private GeneralApplicantService $generalApplicantService,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route(path: '/v1/recruit/general/applicants', methods: [Request::METHOD_POST])]
    #[OA\Post(
        summary: 'Crée un candidat lié au CV du user connecté.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['resumeId'],
                properties: [
                    new OA\Property(property: 'resumeId', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'coverLetter', type: 'string', example: 'Je suis motivé pour ce poste.'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Candidat créé.'),
            new OA\Response(response: 400, description: 'Payload invalide.'),
            new OA\Response(response: 401, description: 'Authentification requise.'),
            new OA\Response(response: 403, description: 'Accès refusé.'),
            new OA\Response(response: 404, description: 'Ressource liée introuvable.'),
        ],
    )]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();

        return new JsonResponse($this->generalApplicantService->create($payload, $loggedInUser), JsonResponse::HTTP_CREATED);
    }
}
