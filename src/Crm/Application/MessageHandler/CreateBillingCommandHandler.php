<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Message\CreateBillingCommand;
use App\Crm\Application\Service\CrmReadCacheInvalidator;
use App\Crm\Domain\Entity\Billing;
use App\Crm\Infrastructure\Repository\BillingRepository;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\General\Application\Message\EntityCreated;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CreateBillingCommandHandler
{
    public function __construct(
        private CompanyRepository $companyRepository,
        private BillingRepository $billingRepository,
        private MessageBusInterface $messageBus,
        private CrmReadCacheInvalidator $cacheInvalidator,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws DateMalformedStringException
     * @throws ORMException
     * @throws ExceptionInterface
     */
    public function __invoke(CreateBillingCommand $command): void
    {
        $company = $this->companyRepository->find($command->companyId);
        if ($company === null) {
            return;
        }

        $billing = new Billing()
            ->setId($command->id)
            ->setCompany($company)
            ->setLabel($command->label)
            ->setAmount($command->amount)
            ->setCurrency($command->currency)
            ->setStatus($command->status);

        if ($command->dueAt !== null) {
            $billing->setDueAt(new DateTimeImmutable($command->dueAt));
        }

        $this->billingRepository->save($billing);

        $this->messageBus->dispatch(new EntityCreated('crm_billing', $billing->getId(), context: [
            'applicationSlug' => $command->applicationSlug,
            'crmId' => $command->crmId,
            'companyId' => $command->companyId,
        ]));

        $this->cacheInvalidator->invalidateBilling($command->applicationSlug, $billing->getId());
    }
}
