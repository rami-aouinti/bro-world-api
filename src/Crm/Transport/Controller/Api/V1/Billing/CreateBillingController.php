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
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Crm\Transport\Request\CrmRequestHandler;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_OWNER->value)]
final readonly class CreateBillingController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CompanyRepository $companyRepository,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmRequestHandler $crmRequestHandler,
        private MessageBusInterface $messageBus,
        private CrmReadCacheInvalidator $cacheInvalidator,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/billings', methods: [Request::METHOD_POST])]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateBillingRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
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

        $dueAt = $this->crmRequestHandler->parseNullableIso8601($input->dueAt, 'dueAt');
        if ($dueAt instanceof JsonResponse) {
            return $dueAt;
        }

        $billing->setDueAt($dueAt);

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

        return new JsonResponse([
            'id' => $billing->getId(),
            'companyId' => $company->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
