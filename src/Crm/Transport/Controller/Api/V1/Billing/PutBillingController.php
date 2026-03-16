<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Billing;

use App\Crm\Application\Message\PutBillingCommand;
use App\Crm\Transport\Request\CreateBillingRequest;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PutBillingController
{
    public function __construct(
        private CrmRequestHandler $crmRequestHandler,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/billings/{billing}', methods: [Request::METHOD_PUT])]
    public function __invoke(string $applicationSlug, string $billing, Request $request): JsonResponse
    {
        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateBillingRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $companyId = (string)($input->companyId ?? '');
        if ($companyId === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'companyId is required.');
        }

        $dueAt = $this->crmRequestHandler->parseNullableIso8601($input->dueAt, 'dueAt');
        if ($dueAt instanceof JsonResponse) {
            return $dueAt;
        }

        $this->messageBus->dispatch(new PutBillingCommand(
            applicationSlug: $applicationSlug,
            billingId: $billing,
            companyId: $companyId,
            label: (string)$input->label,
            amount: (float)$input->amount,
            currency: $input->currency ?: 'EUR',
            status: $input->status ?: 'pending',
            dueAt: $dueAt?->format(DATE_ATOM),
        ));

        return new JsonResponse([
            'id' => $billing,
            'companyId' => $companyId,
        ]);
    }
}
