<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Project;
use App\Crm\Infrastructure\Repository\ProjectRepository;
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
final readonly class AddProjectAssigneeController
{
    public function __construct(private ProjectRepository $projectRepository, private CrmApplicationScopeResolver $scopeResolver, private CrmApiErrorResponseFactory $errorResponseFactory, private EntityManagerInterface $entityManager) {}

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{id}/assignees/{userId}', methods: [Request::METHOD_PUT])]
    public function __invoke(string $applicationSlug, string $id, string $userId): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $project = $this->projectRepository->findOneScopedById($id, $crm->getId());
        if (!$project instanceof Project) { return $this->errorResponseFactory->notFoundReference('projectId'); }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user instanceof User) { return $this->errorResponseFactory->notFoundReference('userId'); }

        $project->addAssignee($user);
        $this->projectRepository->save($project);

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
