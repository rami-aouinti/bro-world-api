<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Exception\CrmReferenceNotFoundException;
use App\Crm\Application\Message\PatchCompanyCommand;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmReadCacheInvalidator;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PatchCompanyCommandHandler
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CompanyRepository $companyRepository,
        private CrmReadCacheInvalidator $cacheInvalidator,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function __invoke(PatchCompanyCommand $command): void
    {
        $crm = $this->scopeResolver->resolveOrFail($command->applicationSlug);
        $company = $this->companyRepository->findOneScopedById($command->companyId, $crm->getId());
        if ($company === null) {
            throw new CrmReferenceNotFoundException('companyId');
        }

        if ($command->hasName) {
            $company->setName((string)$command->name);
        }
        if ($command->hasIndustry) {
            $company->setIndustry($command->industry);
        }
        if ($command->hasWebsite) {
            $company->setWebsite($command->website);
        }
        if ($command->hasContactEmail) {
            $company->setContactEmail($command->contactEmail);
        }
        if ($command->hasPhone) {
            $company->setPhone($command->phone);
        }

        $this->companyRepository->save($company);

        $this->cacheInvalidator->invalidateCompany($command->applicationSlug, $company->getId());
    }
}
