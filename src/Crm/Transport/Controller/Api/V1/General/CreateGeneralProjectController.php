<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\Project;
use App\Crm\Domain\Enum\ProjectStatus;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_string;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class CreateGeneralProjectController
{
    use GeneralCrudApiTrait;

    public function __construct(private EntityManagerInterface $entityManager, private CompanyRepository $companyRepository)
    {
    }

    #[OA\Post(summary: 'General - Create Project', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: ['companyId' => 'uuid', 'name' => 'Migration CRM', 'status' => 'planned'])), responses: [new OA\Response(response: 201, description: 'Project créé', content: new OA\JsonContent(example: ['id' => 'uuid']))])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $companyId = $payload['companyId'] ?? null;
        $name = $payload['name'] ?? null;
        if (!is_string($companyId) || !is_string($name) || $name === '') {
            return $this->badRequest('Fields "companyId" and "name" are required.');
        }

        $company = $this->companyRepository->find($companyId);
        if ($company === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Company not found.');
        }

        $project = (new Project())
            ->setCompany($company)
            ->setName($name)
            ->setCode($this->nullableString($payload['code'] ?? null))
            ->setDescription($this->nullableString($payload['description'] ?? null))
            ->setStatus(ProjectStatus::tryFrom((string) ($payload['status'] ?? 'planned')) ?? ProjectStatus::PLANNED)
            ->setStartedAt($this->parseNullableDate($payload['startedAt'] ?? null))
            ->setDueAt($this->parseNullableDate($payload['dueAt'] ?? null));

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return new JsonResponse(['id' => $project->getId()], JsonResponse::HTTP_CREATED);
    }
}
