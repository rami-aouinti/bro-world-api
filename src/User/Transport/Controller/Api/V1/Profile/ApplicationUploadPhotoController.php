<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\Profile;

use App\General\Application\Service\PhotoUploaderService;
use App\Platform\Domain\Entity\Application;
use App\User\Domain\Entity\User;
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
class ApplicationUploadPhotoController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PhotoUploaderService $photoUploaderService,
    ) {
    }

    #[Route(
        path: '/v1/profile/applications/{application}/photo',
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    #[OA\Post(
        summary: "Upload la photo d'une candidature.",
        tags: ['Profile'],
        parameters: [
            new OA\Parameter(name: 'application', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        requestBody: new OA\RequestBody(
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
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Uploaded application photo URL',
                content: new JsonContent(
                    properties: [
                        new Property(property: 'photo', description: 'Uploaded photo URL', type: 'string'),
                    ],
                    type: 'object',
                    example: [
                        'photo' => 'https://localhost/uploads/applications/0af6fe1514bdbce22f637d970a6e6042.jpg',
                    ],
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'File upload error',
            ),
            new OA\Response(
                response: 404,
                description: 'Application not found for current user',
            ),
        ],
    )]
    public function __invoke(Request $request, User $loggedInUser, Application $application): JsonResponse
    {
        /** @var UploadedFile|null $photo */
        $photo = $request->files->get('photo');

        if (!$photo instanceof UploadedFile) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Missing "photo" file.');
        }

        $photoUrl = $this->photoUploaderService->upload($request, $photo, '/uploads/applications');
        $application->setPhoto($photoUrl);
        $this->entityManager->flush();

        return new JsonResponse([
            'photo' => $photoUrl,
        ]);
    }
}
