<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Infrastructure\Repository\JobRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Job')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class JobDeleteFromApplicationController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JobRepository $jobRepository,
    ) {
    }

    #[Route(path: '/v1/recruit/applications/{applicationSlug}/jobs/{jobId}', methods: [Request::METHOD_DELETE])]
    #[OA\Delete(
        summary: 'Supprime un job via applicationSlug et contrôle propriétaire.',
        parameters: [
            new OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'jobId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Job supprimé.'),
            new OA\Response(response: 403, description: 'Accès interdit.'),
            new OA\Response(response: 404, description: 'Job introuvable.'),
        ],
    )]
    public function __invoke(string $applicationSlug, string $jobId, User $loggedInUser): JsonResponse
    {
        $recruit = $this->resolveRecruitByApplicationSlug($applicationSlug);
        $application = $recruit->getApplication();

        if ($application?->getUser()?->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot delete a job for this application.');
        }

        $job = $this->jobRepository->find($jobId);
        if (!$job instanceof Job) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Job not found.');
        }

        if ($job->getRecruit()?->getId() !== $recruit->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'This job does not belong to the given application.');
        }

        $this->jobRepository->remove($job);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function resolveRecruitByApplicationSlug(string $applicationSlug): Recruit
    {
        $recruit = $this->entityManager
            ->getRepository(Recruit::class)
            ->createQueryBuilder('recruit')
            ->innerJoin('recruit.application', 'application')
            ->addSelect('application')
            ->where('application.slug = :applicationSlug')
            ->setParameter('applicationSlug', $applicationSlug)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$recruit instanceof Recruit) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown "applicationSlug".');
        }

        return $recruit;
    }
}
