<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Company;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Crm\Transport\Request\UpdateCompanyRequest;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use App\Crm\Transport\Request\CrmRequestHandler;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PatchCompanyController
{
    public function __construct(
        private CompanyRepository $companyRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmRequestHandler $crmRequestHandler,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/companies/{companyId}', methods: [Request::METHOD_PATCH])]
    public function __invoke(string $applicationSlug, string $companyId, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $company = $this->companyRepository->findOneScopedById($companyId, $crm->getId());
        if ($company === null) {
            return $this->errorResponseFactory->notFoundReference('companyId');
        }

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, UpdateCompanyRequest::class, mapperMethod: 'fromPatchArray');
        if ($input instanceof JsonResponse) {
            return $input;
        }

        if ($input->hasName) {
            $company->setName((string)$input->name);
        }
        if ($input->hasIndustry) {
            $company->setIndustry($input->industry);
        }
        if ($input->hasWebsite) {
            $company->setWebsite($input->website);
        }
        if ($input->hasContactEmail) {
            $company->setContactEmail($input->contactEmail);
        }
        if ($input->hasPhone) {
            $company->setPhone($input->phone);
        }

        $this->companyRepository->save($company);

        return new JsonResponse([
            'id' => $company->getId(),
        ]);
    }
}
