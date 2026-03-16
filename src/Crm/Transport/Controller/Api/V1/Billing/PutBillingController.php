<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Billing;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmReadCacheInvalidator;
use App\Crm\Infrastructure\Repository\BillingRepository;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Transport\Request\CreateBillingRequest;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Crm\Transport\Request\CrmRequestHandler;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PutBillingController
{
    public function __construct(
        private BillingRepository $billingRepository,
        private CompanyRepository $companyRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmRequestHandler $crmRequestHandler,
        private CrmReadCacheInvalidator $cacheInvalidator,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/billings/{billing}', methods: [Request::METHOD_PUT])]
    public function __invoke(string $applicationSlug, string $billing, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $entity = $this->billingRepository->findOneScopedById($billing, $crm->getId());
        if ($entity === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Billing not found for this CRM scope.');
        }

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateBillingRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $companyId = (string)($input->companyId ?? '');
        if ($companyId === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'companyId is required.');
        }

        $company = $this->companyRepository->findOneScopedById($companyId, $crm->getId());
        if ($company === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Company not found for this CRM scope.');
        }

        $dueAt = $this->crmRequestHandler->parseNullableIso8601($input->dueAt, 'dueAt');
        if ($dueAt instanceof JsonResponse) {
            return $dueAt;
        }

        $entity
            ->setCompany($company)
            ->setLabel((string)$input->label)
            ->setAmount((float)$input->amount)
            ->setCurrency($input->currency ?: 'EUR')
            ->setStatus($input->status ?: 'pending')
            ->setDueAt($dueAt);

        $this->billingRepository->save($entity);

        $this->cacheInvalidator->invalidateBilling($applicationSlug, $entity->getId());

        return new JsonResponse([
            'id' => $entity->getId(),
            'companyId' => $entity->getCompany()?->getId(),
        ]);
    }
}
