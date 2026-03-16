<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Exception\CrmReferenceNotFoundException;
use App\Crm\Application\Message\PatchContactCommand;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmReadCacheInvalidator;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Infrastructure\Repository\ContactRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PatchContactCommandHandler
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private ContactRepository $contactRepository,
        private CompanyRepository $companyRepository,
        private CrmReadCacheInvalidator $cacheInvalidator,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function __invoke(PatchContactCommand $command): void
    {
        $crm = $this->scopeResolver->resolveOrFail($command->applicationSlug);
        $contact = $this->contactRepository->findOneScopedById($command->contactId, $crm->getId());
        if ($contact === null) {
            throw new CrmReferenceNotFoundException('contactId');
        }

        $payload = $command->payload;

        if (isset($payload['firstName'])) {
            $contact->setFirstName((string)$payload['firstName']);
        }
        if (isset($payload['lastName'])) {
            $contact->setLastName((string)$payload['lastName']);
        }
        if (array_key_exists('email', $payload)) {
            $contact->setEmail($payload['email'] !== null ? (string)$payload['email'] : null);
        }
        if (array_key_exists('phone', $payload)) {
            $contact->setPhone($payload['phone'] !== null ? (string)$payload['phone'] : null);
        }
        if (array_key_exists('jobTitle', $payload)) {
            $contact->setJobTitle($payload['jobTitle'] !== null ? (string)$payload['jobTitle'] : null);
        }
        if (array_key_exists('city', $payload)) {
            $contact->setCity($payload['city'] !== null ? (string)$payload['city'] : null);
        }
        if (array_key_exists('score', $payload) && is_numeric($payload['score'])) {
            $contact->setScore((int)$payload['score']);
        }
        if (array_key_exists('companyId', $payload)) {
            if ($payload['companyId'] === null || $payload['companyId'] === '') {
                $contact->setCompany(null);
            } elseif (is_string($payload['companyId'])) {
                $company = $this->companyRepository->findOneScopedById($payload['companyId'], $crm->getId());
                if ($company === null) {
                    throw new CrmReferenceNotFoundException('companyId');
                }

                $contact->setCompany($company);
            }
        }

        $this->contactRepository->save($contact);

        $this->cacheInvalidator->invalidateContact($command->applicationSlug, $contact->getId());
    }
}
