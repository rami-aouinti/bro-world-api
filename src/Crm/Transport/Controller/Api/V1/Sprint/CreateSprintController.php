<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Sprint;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Sprint;
use App\Crm\Domain\Enum\SprintStatus;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Transport\Request\CreateSprintRequest;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\General\Application\Message\EntityCreated;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class CreateSprintController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/sprints', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(summary: 'POST /v1/crm/applications/{applicationSlug}/sprints')]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);

        try {
            $payload = json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        $input = CreateSprintRequest::fromArray($payload);
        $violations = $this->validator->validate($input);
        if ($violations->count() > 0) {
            return $this->errorResponseFactory->validationFailed($violations);
        }

        $startDate = $this->parseDate($input->startDate, 'startDate');
        if ($startDate instanceof JsonResponse) {
            return $startDate;
        }

        $endDate = $this->parseDate($input->endDate, 'endDate');
        if ($endDate instanceof JsonResponse) {
            return $endDate;
        }

        $sprint = new Sprint();
        $sprint->setName((string)$input->name)
            ->setGoal($input->goal)
            ->setStatus(SprintStatus::tryFrom((string)$input->status) ?? SprintStatus::PLANNED)
            ->setStartDate($startDate)
            ->setEndDate($endDate);

        if (is_string($input->projectId)) {
            $project = $this->projectRepository->findOneScopedById($input->projectId, $crm->getId());
            if ($project === null) {
                return $this->errorResponseFactory->notFoundReference('projectId');
            }

            $sprint->setProject($project);
        }

        $this->entityManager->persist($sprint);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_sprint', $sprint->getId()));

        return new JsonResponse([
            'id' => $sprint->getId(),
        ], JsonResponse::HTTP_CREATED);
    }

    private function parseDate(?string $value, string $field): DateTimeImmutable|JsonResponse|null
    {
        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);
        if ($date === false) {
            return $this->errorResponseFactory->invalidDate($field);
        }

        return $date;
    }
}
