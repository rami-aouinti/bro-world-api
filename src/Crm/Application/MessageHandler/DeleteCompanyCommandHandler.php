<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Exception\CrmReferenceNotFoundException;
use App\Crm\Application\Message\DeleteCompanyCommand;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteCompanyCommandHandler
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CompanyRepository $companyRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(DeleteCompanyCommand $command): void
    {
        $crm = $this->scopeResolver->resolveOrFail($command->applicationSlug);
        $company = $this->companyRepository->findOneScopedById($command->companyId, $crm->getId());
        if ($company === null) {
            throw new CrmReferenceNotFoundException('companyId');
        }

        $this->entityManager->remove($company);
        $this->entityManager->flush();
    }
}
