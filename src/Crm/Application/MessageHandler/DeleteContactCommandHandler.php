<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Exception\CrmReferenceNotFoundException;
use App\Crm\Application\Message\DeleteContactCommand;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\ContactRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteContactCommandHandler
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private ContactRepository $contactRepository,
    ) {
    }

    public function __invoke(DeleteContactCommand $command): void
    {
        $crm = $this->scopeResolver->resolveOrFail($command->applicationSlug);
        $contact = $this->contactRepository->findOneScopedById($command->contactId, $crm->getId());
        if ($contact === null) {
            throw new CrmReferenceNotFoundException('contactId');
        }

        $this->contactRepository->remove($contact);
    }
}
