<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\User;

use App\User\Application\Service\UserMeService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'User Me')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class UserMeController
{
    public function __construct(private readonly UserMeService $userMeService)
    {
    }

    #[Route(path: '/v1/users/me', methods: [Request::METHOD_GET])]
    #[OA\Response(response: 200, description: 'Current authenticated user')]
    public function me(User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->userMeService->getMe($loggedInUser));
    }

    #[Route(path: '/v1/users/me/sessions', methods: [Request::METHOD_GET])]
    #[OA\Response(response: 200, description: 'Recent sessions for current user')]
    public function sessions(User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->userMeService->getSessions($loggedInUser));
    }

    #[Route(path: '/v1/users/me/profile', methods: [Request::METHOD_GET])]
    #[OA\Response(response: 200, description: 'Current user profile')]
    public function profile(User $loggedInUser): JsonResponse
    {
        $me = $this->userMeService->getMe($loggedInUser);

        return new JsonResponse($me['profile'] ?? []);
    }

    #[Route(path: '/v1/users/me/profile', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'PATCH /v1/users/me/profile', tags: ['User Me'], parameters: [], responses: [new OA\Response(response: 200, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    public function patchProfile(Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string,mixed> $payload */
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return new JsonResponse($this->userMeService->patchProfile($loggedInUser, $payload));
    }

    #[Route(path: '/v1/users/me/password', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'PATCH /v1/users/me/password', tags: ['User Me'], parameters: [], responses: [new OA\Response(response: 200, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', required: ['currentPassword', 'newPassword']))]
    public function changePassword(Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string,mixed> $payload */
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->userMeService->changePassword($loggedInUser, $payload);

        return new JsonResponse(['status' => 'ok']);
    }

    #[Route(path: '/v1/users/me', methods: [Request::METHOD_DELETE])]
    #[OA\Response(response: 204, description: 'Current user deleted')]
    public function delete(User $loggedInUser): JsonResponse
    {
        $this->userMeService->deleteMe($loggedInUser);

        return new JsonResponse(null, 204);
    }
}
