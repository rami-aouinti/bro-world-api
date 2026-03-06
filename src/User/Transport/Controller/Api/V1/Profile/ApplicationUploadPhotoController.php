<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\Profile;

use App\General\Domain\Rest\UuidHelper;
use App\Platform\Domain\Entity\Application;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function bin2hex;
use function random_bytes;
use function str_starts_with;

#[AsController]
#[OA\Tag(name: 'Profile')]
class ApplicationUploadPhotoController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Filesystem $filesystem,
        private readonly string $projectDir,
    ) {
    }

    #[Route(
        path: '/v1/profile/applications/{applicationId}/photo',
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
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
    )]
    #[OA\Response(
        response: 400,
        description: 'File upload error',
    )]
    #[OA\Response(
        response: 404,
        description: 'Application not found for current user',
    )]
    public function __invoke(Request $request, User $loggedInUser, string $applicationId): JsonResponse
    {
        /** @var Application|null $application */
        $application = $this->entityManager
            ->getRepository(Application::class)
            ->createQueryBuilder('application')
            ->where('application.id = :applicationId')
            ->andWhere('application.user = :loggedInUser')
            ->setParameter('applicationId', $applicationId, UuidHelper::getType($applicationId))
            ->setParameter('loggedInUser', $loggedInUser)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$application instanceof Application) {
            throw new HttpException(Response::HTTP_NOT_FOUND, 'Application not found.');
        }

        /** @var UploadedFile|null $photo */
        $photo = $request->files->get('photo');

        if (!$photo instanceof UploadedFile) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Missing "photo" file.');
        }

        if (!$photo->isValid()) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Invalid uploaded file.');
        }

        if (!str_starts_with((string) $photo->getMimeType(), 'image/')) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Uploaded file must be an image.');
        }

        $extension = $photo->guessExtension() ?? 'bin';
        $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
        $relativeDirectory = '/uploads/applications';
        $targetDirectory = $this->projectDir . '/public' . $relativeDirectory;

        $this->filesystem->mkdir($targetDirectory);
        $photo->move($targetDirectory, $fileName);

        $photoUrl = $request->getSchemeAndHttpHost() . $relativeDirectory . '/' . $fileName;
        $application->setPhoto($photoUrl);
        $this->entityManager->flush();

        return new JsonResponse([
            'photo' => $photoUrl,
        ]);
    }
}
