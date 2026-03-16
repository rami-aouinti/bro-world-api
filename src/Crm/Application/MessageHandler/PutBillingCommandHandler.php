<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Message\PutBillingCommand;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmReadCacheInvalidator;
use App\Crm\Infrastructure\Repository\BillingRepository;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PutBillingCommandHandler
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private BillingRepository $billingRepository,
        private CompanyRepository $companyRepository,
        private CrmReadCacheInvalidator $cacheInvalidator,
    ) {
    }

    public function __invoke(PutBillingCommand $command): void
    {
        $crm = $this->scopeResolver->resolveOrFail($command->applicationSlug);
        $billing = $this->billingRepository->findOneScopedById($command->billingId, $crm->getId());
        if ($billing === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Billing not found for this CRM scope.');
        }

        $company = $this->companyRepository->findOneScopedById($command->companyId, $crm->getId());
        if ($company === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Company not found for this CRM scope.');
        }

        $billing
            ->setCompany($company)
            ->setLabel($command->label)
            ->setAmount($command->amount)
            ->setCurrency($command->currency)
            ->setStatus($command->status)
            ->setDueAt($command->dueAt !== null ? new \DateTimeImmutable($command->dueAt) : null);

        $this->billingRepository->save($billing);
        $this->cacheInvalidator->invalidateBilling($command->applicationSlug, $billing->getId());
    }
}
