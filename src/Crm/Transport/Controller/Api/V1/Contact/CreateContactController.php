<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Contact;

use App\Crm\Application\Message\CreateContactCommand;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Contact;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Transport\Request\CreateContactRequest;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Crm\Transport\Request\CrmRequestHandler;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_ADMIN->value)]
final readonly class CreateContactController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CompanyRepository $companyRepository,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmRequestHandler $crmRequestHandler,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/contacts', methods: [Request::METHOD_POST])]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateContactRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $contact = (new Contact())
            ->setFirstName((string)$input->firstName)
            ->setLastName((string)$input->lastName)
            ->setEmail($input->email)
            ->setPhone($input->phone)
            ->setJobTitle($input->jobTitle)
            ->setCity($input->city)
            ->setScore($input->score ?? 0);

        $companyId = null;
        if (($input->companyId ?? '') !== '') {
            $company = $this->companyRepository->findOneScopedById((string)$input->companyId, $crm->getId());
            if ($company !== null) {
                $companyId = $company->getId();
            }
        }

        $this->messageBus->dispatch(new CreateContactCommand(
            id: $contact->getId(),
            crmId: $crm->getId(),
            firstName: $contact->getFirstName(),
            lastName: $contact->getLastName(),
            email: $contact->getEmail(),
            phone: $contact->getPhone(),
            jobTitle: $contact->getJobTitle(),
            city: $contact->getCity(),
            score: $contact->getScore(),
            companyId: $companyId,
            applicationSlug: $applicationSlug,
        ));

        return new JsonResponse([
            'id' => $contact->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
