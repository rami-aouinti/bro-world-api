<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Company;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Crm\Transport\Request\UpdateCompanyRequest;
use App\Role\Domain\Enum\Role;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PatchCompanyController
{
    public function __construct(
        private CompanyRepository $companyRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private ValidatorInterface $validator,
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

        try {
            $payload = json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        $input = UpdateCompanyRequest::fromPatchArray($payload);
        $violations = $this->validator->validate($input);
        if ($violations->count() > 0) {
            return $this->errorResponseFactory->validationFailed($violations);
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
