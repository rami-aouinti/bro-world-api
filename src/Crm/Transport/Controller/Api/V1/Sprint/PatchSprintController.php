<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Sprint;

use App\Crm\Domain\Entity\Sprint;
use App\Crm\Domain\Enum\SprintStatus;
use App\Crm\Infrastructure\Repository\SprintRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use DateTimeImmutable;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PatchSprintController
{
    public function __construct(
        private SprintRepository $sprintRepository,
        private CrmApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/crm/applications/{applicationSlug}/sprints/{sprint}', methods: [Request::METHOD_PATCH])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'sprint', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Patch(
        summary: 'Patch Sprint dans le CRM',
        description: 'Exécute l action metier Patch Sprint dans le perimetre de l application CRM.',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Opération exécutée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $applicationSlug, Sprint $sprint, Request $request): JsonResponse
    {
        try {
            $payload = json_decode(
                (string)$request->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }
        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (isset($payload['name'])) {
            $sprint->setName((string)$payload['name']);
        }
        if (array_key_exists('goal', $payload)) {
            $sprint->setGoal($payload['goal'] !== null ? (string)$payload['goal'] : null);
        }
        if (isset($payload['status']) && is_string($payload['status'])) {
            $status = SprintStatus::tryFrom($payload['status']);
            if ($status) {
                $sprint->setStatus($status);
            }
        }
        if (array_key_exists('startDate', $payload)) {
            $sprint->setStartDate($this->parseDate($payload['startDate']));
        }
        if (array_key_exists('endDate', $payload)) {
            $sprint->setEndDate($this->parseDate($payload['endDate']));
        }

        $this->sprintRepository->save($sprint);

        return new JsonResponse([
            'id' => $sprint->getId(),
        ]);
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === '' || !is_string($value)) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
