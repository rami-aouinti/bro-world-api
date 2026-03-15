<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project;

use App\Crm\Domain\Entity\Project;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class AddProjectAssigneeController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/crm/applications/{applicationSlug}/projects/{project}/assignees/{userId}', methods: [Request::METHOD_PUT])]
    public function __invoke(string $applicationSlug, Project $project, string $userId): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user instanceof User) {
            return $this->errorResponseFactory->notFoundReference('userId');
        }

        $project->addAssignee($user);
        $this->projectRepository->save($project);

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
