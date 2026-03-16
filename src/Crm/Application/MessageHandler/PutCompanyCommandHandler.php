<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Exception\CrmReferenceNotFoundException;
use App\Crm\Application\Message\PutCompanyCommand;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PutCompanyCommandHandler
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CompanyRepository $companyRepository,
    ) {
    }

    public function __invoke(PutCompanyCommand $command): void
    {
        $crm = $this->scopeResolver->resolveOrFail($command->applicationSlug);
        $company = $this->companyRepository->findOneScopedById($command->companyId, $crm->getId());
        if ($company === null) {
            throw new CrmReferenceNotFoundException('companyId');
        }

        $company
            ->setName($command->name)
            ->setIndustry($command->industry)
            ->setWebsite($command->website)
            ->setContactEmail($command->contactEmail)
            ->setPhone($command->phone);

        $this->companyRepository->save($company);
    }
}
