<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\Task;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
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
final readonly class DeleteGeneralSubTaskController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[OA\Delete(
        summary: 'General - Delete Subtask',
        parameters: [new OA\Parameter(name: 'subtask', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [new OA\Response(response: 204, description: 'Sous-task supprimée')]
    )]
    public function __invoke(Task $subtask): JsonResponse
    {
        if ($subtask->getParentTask() === null) {
            throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Provided task is not a subtask.');
        }

        $this->entityManager->remove($subtask);
        $this->entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
