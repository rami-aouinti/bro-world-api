<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Contact;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Infrastructure\Repository\ContactRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_ADMIN->value)]
final readonly class PatchContactController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private ContactRepository $contactRepository,
        private CompanyRepository $companyRepository,
        private CrmApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/contacts/{id}', methods: [Request::METHOD_PATCH])]
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
                    return $this->errorResponseFactory->notFoundReference('companyId');
                }

                $contact->setCompany($company);
            }
        }

        $this->contactRepository->save($contact);

        return new JsonResponse([
            'id' => $contact->getId(),
        ]);
    }
}
