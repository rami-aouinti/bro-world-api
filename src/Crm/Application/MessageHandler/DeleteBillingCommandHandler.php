<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Message\DeleteBillingCommand;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmReadCacheInvalidator;
use App\Crm\Infrastructure\Repository\BillingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteBillingCommandHandler
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private BillingRepository $billingRepository,
        private EntityManagerInterface $entityManager,
        private CrmReadCacheInvalidator $cacheInvalidator,
    ) {
    }

    public function __invoke(DeleteBillingCommand $command): void
    {
        $crm = $this->scopeResolver->resolveOrFail($command->applicationSlug);
        $billing = $this->billingRepository->findOneScopedById($command->billingId, $crm->getId());
        if ($billing === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Billing not found for this CRM scope.');
        }

        $billingId = $billing->getId();
        $this->entityManager->remove($billing);
        $this->entityManager->flush();

        $this->cacheInvalidator->invalidateBilling($command->applicationSlug, $billingId);
    }
}
