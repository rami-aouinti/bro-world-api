<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Company;

use App\Crm\Domain\Entity\Company;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class ListCompaniesController
{
    public function __construct(
        private CompanyRepository $companyRepository
    ) {
    }

    #[Route('/v1/crm/{applicationSlug}/companies', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug): JsonResponse
    {
        $items = array_map(static fn (Company $company): array => [
            'id' => $company->getId(),
            'name' => $company->getName(),
            'industry' => $company->getIndustry(),
            'website' => $company->getWebsite(),
            'contactEmail' => $company->getContactEmail(),
            'phone' => $company->getPhone(),
        ], $this->companyRepository->findBy([], [
            'createdAt' => 'DESC',
        ], 200));

        return new JsonResponse([
            'items' => $items,
        ]);
    }
}
