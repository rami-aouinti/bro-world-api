<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Message\CreateContactCommand;
use App\Crm\Domain\Entity\Contact;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Infrastructure\Repository\ContactRepository;
use App\Crm\Infrastructure\Repository\CrmRepository;
use App\General\Application\Message\EntityCreated;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CreateContactCommandHandler
{
    public function __construct(
        private CrmRepository $crmRepository,
        private CompanyRepository $companyRepository,
        private ContactRepository $contactRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(CreateContactCommand $command): void
    {
        $crm = $this->crmRepository->find($command->crmId);
        if ($crm === null) {
            return;
        }

        $contact = (new Contact())
            ->setId($command->id)
            ->setCrm($crm)
            ->setFirstName($command->firstName)
            ->setLastName($command->lastName)
            ->setEmail($command->email)
            ->setPhone($command->phone)
            ->setJobTitle($command->jobTitle)
            ->setCity($command->city)
            ->setScore($command->score);

        if ($command->companyId !== null) {
            $company = $this->companyRepository->findOneScopedById($command->companyId, $command->crmId);
            if ($company !== null) {
                $contact->setCompany($company);
            }
        }

        $this->contactRepository->save($contact);

        $this->messageBus->dispatch(new EntityCreated('crm_contact', $contact->getId(), context: [
            'applicationSlug' => $command->applicationSlug,
            'crmId' => $command->crmId,
        ]));
    }
}
