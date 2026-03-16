<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Contact;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Infrastructure\Repository\ContactRepository;
use App\Crm\Transport\Request\CreateContactRequest;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Crm\Transport\Request\CrmRequestHandler;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_ADMIN->value)]
final readonly class PutContactController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private ContactRepository $contactRepository,
        private CompanyRepository $companyRepository,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmRequestHandler $crmRequestHandler,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/contacts/{id}', methods: [Request::METHOD_PUT])]
    public function __invoke(string $applicationSlug, string $id, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $contact = $this->contactRepository->findOneScopedById($id, $crm->getId());
        if ($contact === null) {
            return $this->errorResponseFactory->notFoundReference('contactId');
        }

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateContactRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $contact
            ->setFirstName((string)$input->firstName)
            ->setLastName((string)$input->lastName)
            ->setEmail($input->email)
            ->setPhone($input->phone)
            ->setJobTitle($input->jobTitle)
            ->setCity($input->city)
            ->setScore($input->score ?? 0)
            ->setCompany(null);

        if (($input->companyId ?? '') !== '') {
            $company = $this->companyRepository->findOneScopedById((string)$input->companyId, $crm->getId());
            if ($company === null) {
                return $this->errorResponseFactory->notFoundReference('companyId');
            }

            $contact->setCompany($company);
        }

        $this->contactRepository->save($contact);

        return new JsonResponse([
            'id' => $contact->getId(),
        ]);
    }
}
