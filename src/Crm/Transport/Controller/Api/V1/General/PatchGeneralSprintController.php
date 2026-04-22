<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\Sprint;
use App\Crm\Domain\Enum\SprintStatus;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_string;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PatchGeneralSprintController
{
    use GeneralCrudApiTrait;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[OA\Patch(summary: 'General - Update Sprint', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: ['status' => 'active', 'goal' => 'Livrer les stories critiques'])), responses: [new OA\Response(response: 200, description: 'Sprint mise à jour', content: new OA\JsonContent(example: ['id' => 'uuid']))])]
    public function __invoke(Sprint $sprint, Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if (isset($payload['name']) && is_string($payload['name']) && $payload['name'] !== '') {
            $sprint->setName($payload['name']);
        }

        if (isset($payload['goal'])) {
            $sprint->setGoal($this->nullableString($payload['goal']));
        }

        if (isset($payload['status'])) {
            $sprint->setStatus(SprintStatus::tryFrom((string) $payload['status']) ?? SprintStatus::PLANNED);
        }

        if (isset($payload['startDate'])) {
            $sprint->setStartDate($this->parseNullableDate($payload['startDate']));
        }

        if (isset($payload['endDate'])) {
            $sprint->setEndDate($this->parseNullableDate($payload['endDate']));
        }

        $this->entityManager->flush();

        return new JsonResponse(['id' => $sprint->getId()]);
    }
}
