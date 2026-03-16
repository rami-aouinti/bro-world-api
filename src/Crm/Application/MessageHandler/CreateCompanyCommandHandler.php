<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Message\CreateCompanyCommand;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmReadCacheInvalidator;
use App\Crm\Domain\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateCompanyCommandHandler
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private EntityManagerInterface $entityManager,
        private CrmReadCacheInvalidator $cacheInvalidator,
    ) {
    }

    public function __invoke(CreateCompanyCommand $command): void
    {
        $crm = $this->scopeResolver->resolveOrFail($command->applicationSlug);

        $company = new Company()
            ->setCrm($crm)
            ->setName($command->name)
            ->setIndustry($command->industry)
            ->setWebsite($command->website)
            ->setContactEmail($command->contactEmail)
            ->setPhone($command->phone);

        $this->entityManager->persist($company);
        $this->entityManager->flush();

        $this->cacheInvalidator->invalidateCompany($command->applicationSlug, $company->getId());
    }
}
