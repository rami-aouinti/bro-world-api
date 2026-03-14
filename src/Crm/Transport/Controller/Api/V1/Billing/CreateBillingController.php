<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Billing;

use App\Crm\Application\Message\CreateBillingCommand;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmReadCacheInvalidator;
use App\Crm\Domain\Entity\Billing;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Transport\Request\CreateBillingRequest;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use DateTimeImmutable;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_OWNER->value)]
final readonly class CreateBillingController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CompanyRepository $companyRepository,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private ValidatorInterface $validator,
        private MessageBusInterface $messageBus,
        private CrmReadCacheInvalidator $cacheInvalidator,
    ) {}

    #[Route('/v1/crm/applications/{applicationSlug}/billings', methods: [Request::METHOD_POST])]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);

        try {
            $payload = json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        $input = CreateBillingRequest::fromArray($payload);
        $violations = $this->validator->validate($input);
        if ($violations->count() > 0) {
            return $this->errorResponseFactory->validationFailed($violations);
        }

        $company = $this->companyRepository->findOneScopedById((string)$input->companyId, $crm->getId());
        if ($company === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Company not found for this CRM scope.');
        }

        $billing = (new Billing())
            ->setLabel((string)$input->label)
            ->setAmount((float)$input->amount)
            ->setCurrency($input->currency ?: 'EUR')
            ->setStatus($input->status ?: 'pending');

        if (($input->dueAt ?? '') !== '') {
            $billing->setDueAt(new DateTimeImmutable((string)$input->dueAt));
        }

        $this->messageBus->dispatch(new CreateBillingCommand(
            id: $billing->getId(),
            companyId: $company->getId(),
            label: $billing->getLabel(),
            amount: $billing->getAmount(),
            currency: $billing->getCurrency(),
            status: $billing->getStatus(),
            dueAt: $billing->getDueAt()?->format(DATE_ATOM),
            applicationSlug: $applicationSlug,
            crmId: $crm->getId(),
        ));

        $this->cacheInvalidator->invalidateBilling($applicationSlug, $billing->getId());

        return new JsonResponse(['id' => $billing->getId(), 'companyId' => $company->getId()], JsonResponse::HTTP_CREATED);
    }
}
