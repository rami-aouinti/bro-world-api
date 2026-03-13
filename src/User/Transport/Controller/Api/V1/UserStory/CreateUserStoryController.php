<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\UserStory;

use App\General\Application\Service\PhotoUploaderService;
use App\User\Application\Service\UserStoryService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Stories')]
final readonly class CreateUserStoryController
{
    public function __construct(
        private UserStoryService $userStoryService,
        private PhotoUploaderService $photoUploaderService,
    ) {
    }

    #[Route('/v1/private/stories', methods: [Request::METHOD_POST])]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                type: 'object',
                required: ['photo'],
                properties: [
                    new OA\Property(property: 'photo', type: 'string', format: 'binary'),
                ],
            ),
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Created story',
        content: new JsonContent(
            type: 'object',
            properties: [
                new Property(property: 'id', type: 'string'),
                new Property(property: 'imageUrl', type: 'string'),
                new Property(property: 'createdAt', type: 'string', format: 'date-time'),
                new Property(property: 'expiresAt', type: 'string', format: 'date-time'),
            ],
        ),
    )]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    public function __invoke(User $loggedInUser, Request $request): JsonResponse
    {
        /** @var UploadedFile|null $photo */
        $photo = $request->files->get('photo');
        if (!$photo instanceof UploadedFile) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Missing "photo" file.');
        }

        $photoUrl = $this->photoUploaderService->upload($request, $photo, '/uploads/stories');
        $story = $this->userStoryService->createStory($loggedInUser, $photoUrl);

        return new JsonResponse($story, JsonResponse::HTTP_CREATED);
    }
}
