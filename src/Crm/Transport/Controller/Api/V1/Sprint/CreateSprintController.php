<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Sprint;

use App\Crm\Domain\Entity\Sprint;
use App\Crm\Domain\Enum\SprintStatus;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\General\Application\Message\EntityCreated;
use DateTimeImmutable;
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
final readonly class CreateSprintController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/{applicationSlug}/sprints', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(summary: 'POST /v1/crm/{applicationSlug}/sprints')]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $payload = (array)json_decode((string)$request->getContent(), true);
        $sprint = new Sprint();
        $sprint->setName((string)($payload['name'] ?? ''))
            ->setGoal(isset($payload['goal']) ? (string)$payload['goal'] : null)
            ->setStatus(SprintStatus::tryFrom((string)($payload['status'] ?? '')) ?? SprintStatus::PLANNED)
            ->setStartDate(isset($payload['startDate']) ? new DateTimeImmutable((string)$payload['startDate']) : null)
            ->setEndDate(isset($payload['endDate']) ? new DateTimeImmutable((string)$payload['endDate']) : null);
        if (is_string($payload['projectId'] ?? null)) {
            $sprint->setProject($this->projectRepository->find($payload['projectId']));
        }

        $this->entityManager->persist($sprint);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_sprint', $sprint->getId()));

        return new JsonResponse([
            'id' => $sprint->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
