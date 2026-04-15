<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\Project;
use App\Crm\Domain\Enum\ProjectStatus;
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
final readonly class PatchGeneralProjectController
{
    use GeneralCrudApiTrait;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('/v1/crm/general/projects/{project}', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'General - Update Project', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: ['name' => 'Migration CRM v2', 'status' => 'active'])), responses: [new OA\Response(response: 200, description: 'Project mis à jour', content: new OA\JsonContent(example: ['id' => 'uuid']))])]
    public function __invoke(Project $project, Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if (isset($payload['name']) && is_string($payload['name']) && $payload['name'] !== '') {
            $project->setName($payload['name']);
        }

        if (isset($payload['code'])) {
            $project->setCode($this->nullableString($payload['code']));
        }

        if (isset($payload['description'])) {
            $project->setDescription($this->nullableString($payload['description']));
        }

        if (isset($payload['status'])) {
            $project->setStatus(ProjectStatus::tryFrom((string) $payload['status']) ?? ProjectStatus::PLANNED);
        }

        if (isset($payload['startedAt'])) {
            $project->setStartedAt($this->parseNullableDate($payload['startedAt']));
        }

        if (isset($payload['dueAt'])) {
            $project->setDueAt($this->parseNullableDate($payload['dueAt']));
        }

        $this->entityManager->flush();

        return new JsonResponse(['id' => $project->getId()]);
    }
}
