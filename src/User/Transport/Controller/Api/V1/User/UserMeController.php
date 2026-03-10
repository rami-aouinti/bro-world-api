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
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'title', type: 'string', nullable: true, example: 'Lead Developer'),
                new OA\Property(property: 'information', type: 'string', nullable: true, example: 'Disponible pour des projets remote en Europe.'),
                new OA\Property(property: 'gender', type: 'string', nullable: true, enum: ['Female', 'Male'], example: 'Male'),
                new OA\Property(property: 'birthday', type: 'string', format: 'date', nullable: true, example: '1993-07-14'),
                new OA\Property(property: 'location', type: 'string', nullable: true, example: 'Paris'),
                new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+33 6 11 22 33 44'),
                new OA\Property(property: 'firstName', type: 'string', nullable: true, example: 'Alexandre'),
                new OA\Property(property: 'lastName', type: 'string', nullable: true, example: 'Martin'),
                new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, example: 'alexandre.martin@example.com'),
                new OA\Property(
                    property: 'socials',
                    type: 'array',
                    nullable: true,
                    items: new OA\Items(
                        type: 'object',
                        required: ['provider', 'providerId'],
                        properties: [
                            new OA\Property(property: 'provider', type: 'string', example: 'linkedin'),
                            new OA\Property(property: 'providerId', type: 'string', example: 'alex-martin'),
                        ],
                    ),
                ),
            ],
            example: [
                'title' => 'Lead Developer',
                'information' => 'Disponible pour des projets remote en Europe.',
                'gender' => 'Male',
                'birthday' => '1993-07-14',
                'location' => 'Paris',
                'phone' => '+33 6 11 22 33 44',
                'firstName' => 'Alexandre',
                'lastName' => 'Martin',
                'email' => 'alexandre.martin@example.com',
                'socials' => [
                    ['provider' => 'linkedin', 'providerId' => 'alex-martin'],
                    ['provider' => 'github', 'providerId' => 'alexmartin-dev'],
                ],
            ],
        )
    )]
    #[OA\Response(response: 200, description: 'Updated profile')]
    public function patchProfile(Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string,mixed> $payload */
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return new JsonResponse($this->userMeService->patchProfile($loggedInUser, $payload));
    }

    #[Route(path: '/v1/users/me/password', methods: [Request::METHOD_PATCH])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['currentPassword', 'newPassword'],
            properties: [
                new OA\Property(property: 'currentPassword', type: 'string', minLength: 1, example: 'CurrentPass!2024'),
                new OA\Property(property: 'newPassword', type: 'string', minLength: 1, example: 'MyNewStrongPass!2026'),
            ],
            example: [
                'currentPassword' => 'CurrentPass!2024',
                'newPassword' => 'MyNewStrongPass!2026',
            ],
        )
    )]
    #[OA\Response(response: 200, description: 'Password changed', content: new OA\JsonContent(example: ['status' => 'ok']))]
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
