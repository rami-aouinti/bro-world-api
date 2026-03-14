<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Class;

use App\School\Application\Service\CreateClassByApplicationService;
use App\School\Application\Service\SchoolApplicationScopeResolver;
use App\School\Transport\Controller\Api\V1\Input\CreateClassByApplicationInput;
use App\School\Transport\Controller\Api\V1\Input\SchoolInputValidator;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'School')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class CreateClassByApplicationController
{
    public function __construct(
        private SchoolApplicationScopeResolver $scopeResolver,
        private CreateClassByApplicationService $createClassByApplicationService,
        private SchoolInputValidator $inputValidator,
    ) {
    }

    #[Route('/v1/school/applications/{applicationSlug}/classes', methods: [Request::METHOD_POST])]
    #[OA\Post(
        summary: 'Créer une classe dans une application',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Terminale S'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Classe créée.', content: new OA\JsonContent(example: ['id' => '7600e750-f92f-4f9f-883a-26404b538f66', 'schoolId' => 'b7c23d65-11e0-4f26-8ad7-3f58c48f1290', 'applicationSlug' => 'school-crm'])),
            new OA\Response(response: 403, description: 'Accès refusé.'),
            new OA\Response(response: 404, description: 'Application introuvable.'),
            new OA\Response(response: 422, description: 'Erreur de validation.'),
        ],
    )]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request, ?User $loggedInUser): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);
        $payload = $request->toArray();

        $input = new CreateClassByApplicationInput();
        $input->name = (string)($payload['name'] ?? '');

        $validationResponse = $this->inputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $class = $this->createClassByApplicationService->create($applicationSlug, $school, $input->name);

        return new JsonResponse([
            'id' => $class->getId(),
            'schoolId' => $school->getId(),
            'applicationSlug' => $applicationSlug,
        ], JsonResponse::HTTP_CREATED);
    }
}
