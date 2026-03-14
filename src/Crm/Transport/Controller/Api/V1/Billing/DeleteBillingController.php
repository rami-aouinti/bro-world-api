<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Billing;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmReadCacheInvalidator;
use App\Crm\Infrastructure\Repository\BillingRepository;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class DeleteBillingController
{
    public function __construct(
        private BillingRepository $billingRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private EntityManagerInterface $entityManager,
        private CrmReadCacheInvalidator $cacheInvalidator,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/billings/{billing}', methods: [Request::METHOD_DELETE])]
    public function __invoke(string $applicationSlug, string $billing): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $entity = $this->billingRepository->findOneScopedById($billing, $crm->getId());
        if ($entity === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Billing not found for this CRM scope.');
        }

        $this->entityManager->remove($entity);
        $billingId = $entity->getId();
        $this->entityManager->flush();
        $this->cacheInvalidator->invalidateBilling($applicationSlug, $billingId);

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
