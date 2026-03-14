<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Contact;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Infrastructure\Repository\ContactRepository;
use App\Crm\Transport\Request\CreateContactRequest;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
        private ValidatorInterface $validator,
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

        try {
            $payload = json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        $input = CreateContactRequest::fromArray($payload);
        $violations = $this->validator->validate($input);
        if ($violations->count() > 0) {
            return $this->errorResponseFactory->validationFailed($violations);
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

        return new JsonResponse(['id' => $contact->getId()]);
    }
}
