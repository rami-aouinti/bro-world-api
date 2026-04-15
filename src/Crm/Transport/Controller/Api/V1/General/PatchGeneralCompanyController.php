<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\Company;
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
final readonly class PatchGeneralCompanyController
{
    use GeneralCrudApiTrait;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('/v1/crm/general/companies/{company}', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(
        summary: 'General - Update Company',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: ['name' => 'Acme Updated', 'industry' => 'Retail'])),
        responses: [new OA\Response(response: 200, description: 'Company mise à jour', content: new OA\JsonContent(example: ['id' => '3b7044ba-1f2e-4f62-b07f-cf8d77ccf970']))],
    )]
    public function __invoke(Company $company, Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if (isset($payload['name']) && is_string($payload['name']) && $payload['name'] !== '') {
            $company->setName($payload['name']);
        }

        if (isset($payload['industry'])) {
            $company->setIndustry($this->nullableString($payload['industry']));
        }

        if (isset($payload['website'])) {
            $company->setWebsite($this->nullableString($payload['website']));
        }

        if (isset($payload['contactEmail'])) {
            $company->setContactEmail($this->nullableString($payload['contactEmail']));
        }

        if (isset($payload['phone'])) {
            $company->setPhone($this->nullableString($payload['phone']));
        }

        $this->entityManager->flush();

        return new JsonResponse(['id' => $company->getId()]);
    }
}
