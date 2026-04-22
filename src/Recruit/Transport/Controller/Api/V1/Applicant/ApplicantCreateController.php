<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Applicant;

use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Infrastructure\Repository\ApplicantRepository;
use App\Recruit\Infrastructure\Repository\ResumeRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_string;
use function trim;

#[AsController]
#[OA\Tag(name: 'Recruit Applicant')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class ApplicantCreateController
{
    public function __construct(
        private ApplicantRepository $applicantRepository,
        private ResumeRepository $resumeRepository,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route(path: '/v1/recruit/applicants', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
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
        ],
    )]
    public function __invoke(string $applicationSlug, Request $request, User $loggedInUser): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();

        $resumeId = $payload['resumeId'] ?? null;
        $coverLetter = $payload['coverLetter'] ?? '';

        if (!is_string($resumeId) || !Uuid::isValid($resumeId)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "resumeId" must be a valid UUID.');
        }

        if (!is_string($coverLetter)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "coverLetter" must be a string.');
        }

        $resume = $this->resumeRepository->find($resumeId);
        if ($resume === null) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown "resumeId".');
        }

        if ($resume->getOwner()->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'The given resume does not belong to the authenticated user.');
        }

        $applicant = new Applicant()
            ->setUser($loggedInUser)
            ->setResume($resume)
            ->setCoverLetter(trim($coverLetter));

        $this->applicantRepository->save($applicant);

        return new JsonResponse([
            'id' => $applicant->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
