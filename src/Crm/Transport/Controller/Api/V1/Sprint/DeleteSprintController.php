<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Sprint;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Sprint;
use App\Crm\Infrastructure\Repository\SprintRepository;
use App\General\Application\Message\EntityDeleted;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class DeleteSprintController
{
    public function __construct(
        private SprintRepository $sprintRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/sprints/{id}', methods: [Request::METHOD_DELETE])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, string $id): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $sprint = $this->sprintRepository->findOneScopedById($id, $crm->getId());
        if (!$sprint instanceof Sprint) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($sprint);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('crm_sprint', $id));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
