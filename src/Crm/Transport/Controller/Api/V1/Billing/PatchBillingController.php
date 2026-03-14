<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Billing;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\BillingRepository;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PatchBillingController
{
    public function __construct(
        private BillingRepository $billingRepository,
        private CompanyRepository $companyRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/billings/{billing}', methods: [Request::METHOD_PATCH])]
    public function __invoke(string $applicationSlug, string $billing, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $entity = $this->billingRepository->findOneScopedById($billing, $crm->getId());
        if ($entity === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Billing not found for this CRM scope.');
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (array_key_exists('companyId', $payload)) {
            if ($payload['companyId'] === null || $payload['companyId'] === '') {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'companyId cannot be null or empty.');
            }

            if (is_string($payload['companyId'])) {
                $company = $this->companyRepository->findOneScopedById($payload['companyId'], $crm->getId());
                if ($company === null) {
                    throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Company not found for this CRM scope.');
                }

                $entity->setCompany($company);
            }
        }

        if (isset($payload['label'])) {
            $entity->setLabel((string) $payload['label']);
        }
        if (array_key_exists('amount', $payload)) {
            $entity->setAmount(is_numeric($payload['amount']) ? (float) $payload['amount'] : 0.0);
        }
        if (isset($payload['currency'])) {
            $entity->setCurrency((string) $payload['currency']);
        }
        if (isset($payload['status'])) {
            $entity->setStatus((string) $payload['status']);
        }
        if (array_key_exists('dueAt', $payload)) {
            $entity->setDueAt($this->parseDate($payload['dueAt']));
        }
        if (array_key_exists('paidAt', $payload)) {
            $entity->setPaidAt($this->parseDate($payload['paidAt']));
        }

        $this->billingRepository->save($entity);

        return new JsonResponse(['id' => $entity->getId()]);
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '' || !is_string($value)) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);

        return $parsed === false ? null : $parsed;
    }
}
