<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project;

use App\Crm\Domain\Entity\Project;
use App\Crm\Domain\Enum\ProjectStatus;
use App\Crm\Infrastructure\Repository\CompanyRepository;
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
final readonly class CreateProjectController
{
    public function __construct(
        private CompanyRepository $companyRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/{applicationSlug}/projects', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(summary: 'POST /v1/crm/{applicationSlug}/projects')]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $payload = (array)json_decode((string)$request->getContent(), true);
        $project = new Project();
        $project->setName((string)($payload['name'] ?? ''))
            ->setCode(isset($payload['code']) ? (string)$payload['code'] : null)
            ->setDescription(isset($payload['description']) ? (string)$payload['description'] : null)
            ->setStatus(ProjectStatus::tryFrom((string)($payload['status'] ?? '')) ?? ProjectStatus::PLANNED)
            ->setStartedAt(isset($payload['startedAt']) ? new DateTimeImmutable((string)$payload['startedAt']) : null)
            ->setDueAt(isset($payload['dueAt']) ? new DateTimeImmutable((string)$payload['dueAt']) : null);
        if (is_string($payload['companyId'] ?? null)) {
            $project->setCompany($this->companyRepository->find($payload['companyId']));
        }

        $this->entityManager->persist($project);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_project', $project->getId()));

        return new JsonResponse([
            'id' => $project->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
