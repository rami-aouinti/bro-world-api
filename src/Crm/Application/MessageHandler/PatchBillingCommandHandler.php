<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Message\PatchBillingCommand;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmReadCacheInvalidator;
use App\Crm\Infrastructure\Repository\BillingRepository;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PatchBillingCommandHandler
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private BillingRepository $billingRepository,
        private CompanyRepository $companyRepository,
        private CrmReadCacheInvalidator $cacheInvalidator,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function __invoke(PatchBillingCommand $command): void
    {
        $crm = $this->scopeResolver->resolveOrFail($command->applicationSlug);
        $billing = $this->billingRepository->findOneScopedById($command->billingId, $crm->getId());
        if ($billing === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Billing not found for this CRM scope.');
        }

        $payload = $command->payload;

        if (array_key_exists('companyId', $payload)) {
            if ($payload['companyId'] === null || $payload['companyId'] === '') {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'companyId cannot be null or empty.');
            }

            if (is_string($payload['companyId'])) {
                $company = $this->companyRepository->findOneScopedById($payload['companyId'], $crm->getId());
                if ($company === null) {
                    throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Company not found for this CRM scope.');
                }

                $billing->setCompany($company);
            }
        }

        if (isset($payload['label'])) {
            $billing->setLabel((string)$payload['label']);
        }
        if (array_key_exists('amount', $payload)) {
            $billing->setAmount(is_numeric($payload['amount']) ? (float)$payload['amount'] : 0.0);
        }
        if (isset($payload['currency'])) {
            $billing->setCurrency((string)$payload['currency']);
        }
        if (isset($payload['status'])) {
            $billing->setStatus((string)$payload['status']);
        }
        if (array_key_exists('dueAt', $payload)) {
            $billing->setDueAt($this->parseDate($payload['dueAt']));
        }
        if (array_key_exists('paidAt', $payload)) {
            $billing->setPaidAt($this->parseDate($payload['paidAt']));
        }

        $this->billingRepository->save($billing);
        $this->cacheInvalidator->invalidateBilling($command->applicationSlug, $billing->getId());
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === '' || !is_string($value)) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);

        return $parsed === false ? null : $parsed;
    }
}
