<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Billing;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\BillingRepository;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class GetBillingController
{
    public function __construct(
        private BillingRepository $billingRepository,
        private CrmApplicationScopeResolver $scopeResolver,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/billings/{billing}', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, string $billing): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $entity = $this->billingRepository->findOneScopedById($billing, $crm->getId());
        if ($entity === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Billing not found for this CRM scope.');
        }

        return new JsonResponse([
            'id' => $entity->getId(),
            'companyId' => $entity->getCompany()?->getId(),
            'label' => $entity->getLabel(),
            'amount' => $entity->getAmount(),
            'currency' => $entity->getCurrency(),
            'status' => $entity->getStatus(),
            'dueAt' => $entity->getDueAt()?->format(DATE_ATOM),
            'paidAt' => $entity->getPaidAt()?->format(DATE_ATOM),
        ]);
    }
}
