<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Sprint;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Sprint;
use App\Crm\Infrastructure\Repository\SprintRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class AddSprintAssigneeController
{
    public function __construct(private SprintRepository $sprintRepository, private CrmApplicationScopeResolver $scopeResolver, private CrmApiErrorResponseFactory $errorResponseFactory, private EntityManagerInterface $entityManager) {}

    #[Route('/v1/crm/applications/{applicationSlug}/sprints/{id}/assignees/{userId}', methods: [Request::METHOD_PUT])]
    public function __invoke(string $applicationSlug, string $id, string $userId): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $sprint = $this->sprintRepository->findOneScopedById($id, $crm->getId());
        if (!$sprint instanceof Sprint) { return $this->errorResponseFactory->notFoundReference('sprintId'); }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user instanceof User) { return $this->errorResponseFactory->notFoundReference('userId'); }

        $sprint->addAssignee($user);
        $this->sprintRepository->save($sprint);

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
