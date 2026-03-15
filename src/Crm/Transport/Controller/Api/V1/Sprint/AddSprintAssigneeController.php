<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Sprint;

use App\Crm\Domain\Entity\Sprint;
use App\Crm\Infrastructure\Repository\SprintRepository;
use App\Role\Domain\Enum\Role;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class AddSprintAssigneeController
{
    public function __construct(
        private SprintRepository $sprintRepository
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/crm/applications/{applicationSlug}/sprints/{sprint}/assignees/{user}', methods: [Request::METHOD_PUT])]
    public function __invoke(string $applicationSlug, Sprint $sprint, User $user): JsonResponse
    {
        $sprint->addAssignee($user);
        $this->sprintRepository->save($sprint);

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
