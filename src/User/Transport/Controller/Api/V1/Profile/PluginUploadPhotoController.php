<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\Profile;

use App\General\Application\Service\PhotoUploaderService;
use App\Platform\Domain\Entity\Plugin;
use Doctrine\ORM\EntityManagerInterface;
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
#[OA\Tag(name: 'Profile')]
class PluginUploadPhotoController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PhotoUploaderService $photoUploaderService,
    ) {
    }

    #[Route(
        path: '/v1/profile/plugins/{plugin}/photo',
        methods: [Request::METHOD_POST],
    )]
    #[OA\Post(summary: 'POST /v1/profile/plugins/{plugin}/photo', tags: ['Profile'], parameters: [new OA\Parameter(name: 'plugin', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 201, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['photo'],
                properties: [
                    new OA\Property(property: 'photo', type: 'string', format: 'binary'),
                ],
                type: 'object',
            ),
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Uploaded plugin photo URL',
        content: new JsonContent(
            properties: [
                new Property(property: 'photo', description: 'Uploaded photo URL', type: 'string'),
            ],
            type: 'object',
            example: [
                'photo' => 'https://localhost/uploads/plugins/0af6fe1514bdbce22f637d970a6e6042.jpg',
            ],
        ),
    )]
    #[OA\Response(
        response: 400,
        description: 'File upload error',
    )]
    public function __invoke(Request $request, Plugin $plugin): JsonResponse
    {
        /** @var UploadedFile|null $photo */
        $photo = $request->files->get('photo');

        if (!$photo instanceof UploadedFile) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Missing "photo" file.');
        }

        $photoUrl = $this->photoUploaderService->upload($request, $photo, '/uploads/plugins');
        $plugin->setPhoto($photoUrl);
        $this->entityManager->flush();

        return new JsonResponse([
            'photo' => $photoUrl,
        ]);
    }
}
