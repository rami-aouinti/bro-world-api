<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\General;

use App\Recruit\Application\Service\JobPublicListService;
use App\Recruit\Infrastructure\Repository\ApplicantRepository;
use App\User\Domain\Entity\User;
use JsonException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit General Job')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class ListGeneralApplicantsController
{
    public function __construct(
        private ApplicantRepository $applicantRepository,
    ) {
    }

    /**
     * @param User $loggedInUser
     * @return JsonResponse
     */
    #[Route(path: '/v1/recruit/general/applicants', methods: [Request::METHOD_GET])]
    public function __invoke(User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->applicantRepository->findByUser($loggedInUser->getId()));
    }
}
