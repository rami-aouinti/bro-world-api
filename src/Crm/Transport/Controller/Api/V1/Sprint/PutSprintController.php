<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Sprint;

use App\Crm\Application\Dto\Response\EntityIdResponseDto;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Enum\SprintStatus;
use App\Crm\Infrastructure\Repository\SprintRepository;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Crm\Transport\Request\PutSprintRequest;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PutSprintController
{
    public function __construct(
        private SprintRepository $sprintRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmRequestHandler $crmRequestHandler,
    ) {
    }

    #[Route('/v1/crm/sprints/{sprint}', methods: [Request::METHOD_PUT])]
    #[OA\Put(
        summary: 'Replace Sprint',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Sprint replaced.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Invalid JSON payload or invalid date format.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Sprint not found in CRM scope.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation failed.'),
        ],
    )]
    public function __invoke(string $applicationSlug, string $sprint, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $entity = $this->sprintRepository->findOneScopedById($sprint, $crm->getId());
        if ($entity === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Sprint not found for this CRM scope.');
        }

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, PutSprintRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $startDate = $this->crmRequestHandler->parseNullableIso8601($input->startDate, 'startDate');
        if ($startDate instanceof JsonResponse) {
            return $startDate;
        }

        $endDate = $this->crmRequestHandler->parseNullableIso8601($input->endDate, 'endDate');
        if ($endDate instanceof JsonResponse) {
            return $endDate;
        }

        $entity
            ->setName((string)$input->name)
            ->setGoal($input->goal)
            ->setStatus(SprintStatus::from((string)$input->status))
            ->setStartDate($startDate)
            ->setEndDate($endDate);

        $this->sprintRepository->save($entity);

        return new JsonResponse((new EntityIdResponseDto($entity->getId()))->toArray());
    }
}
