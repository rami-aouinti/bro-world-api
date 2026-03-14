<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Company;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Company;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use App\Crm\Application\Security\CrmPermissions;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(CrmPermissions::VIEW)]
final readonly class GetCompanyController
{
    public function __construct(private CompanyRepository $companyRepository, private CrmApplicationScopeResolver $scopeResolver, private CrmApiErrorResponseFactory $errorResponseFactory) {}

    #[Route('/v1/crm/applications/{applicationSlug}/companies/{id}', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, Company $company): JsonResponse
    {
        return new JsonResponse([
            'id' => $company->getId(),
            'name' => $company->getName(),
            'industry' => $company->getIndustry(),
            'website' => $company->getWebsite(),
            'contactEmail' => $company->getContactEmail(),
            'phone' => $company->getPhone(),
        ]);
    }
}
