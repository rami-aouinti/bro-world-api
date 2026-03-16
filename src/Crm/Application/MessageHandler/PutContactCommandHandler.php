<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Exception\CrmReferenceNotFoundException;
use App\Crm\Application\Message\PutContactCommand;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Infrastructure\Repository\ContactRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PutContactCommandHandler
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private ContactRepository $contactRepository,
        private CompanyRepository $companyRepository,
    ) {
    }

    public function __invoke(PutContactCommand $command): void
    {
        $crm = $this->scopeResolver->resolveOrFail($command->applicationSlug);
        $contact = $this->contactRepository->findOneScopedById($command->contactId, $crm->getId());
        if ($contact === null) {
            throw new CrmReferenceNotFoundException('contactId');
        }

        $contact
            ->setFirstName($command->firstName)
            ->setLastName($command->lastName)
            ->setEmail($command->email)
            ->setPhone($command->phone)
            ->setJobTitle($command->jobTitle)
            ->setCity($command->city)
            ->setScore($command->score)
            ->setCompany(null);

        if (($command->companyId ?? '') !== '') {
            $company = $this->companyRepository->findOneScopedById((string)$command->companyId, $crm->getId());
            if ($company === null) {
                throw new CrmReferenceNotFoundException('companyId');
            }

            $contact->setCompany($company);
        }

        $this->contactRepository->save($contact);
    }
}
