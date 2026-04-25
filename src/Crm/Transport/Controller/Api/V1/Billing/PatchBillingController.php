<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Billing;

use App\Crm\Application\Dto\Command\UpdateBillingCommandDto;
use App\Crm\Application\Dto\Response\EntityIdResponseDto;
use App\Crm\Application\Message\PatchBillingCommand;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PatchBillingController
{
    public function __construct(
        private CrmRequestHandler $crmRequestHandler,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/v1/crm/billings/{billing}', methods: [Request::METHOD_PATCH])]
        #[OA\Parameter(name: 'billing', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Patch(
        description: 'Exécute l action metier Patch Billing dans le perimetre de l application CRM.',
        summary: 'Patch Billing',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Opération exécutée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $billing, Request $request): JsonResponse
    {
        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, UpdateBillingCommandDto::class, mapperMethod: 'fromPatchArray');
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $mappedPayload = [];
        if ($input->hasCompanyId) {
            $mappedPayload['companyId'] = $input->companyId;
        }
        if ($input->hasLabel) {
            $mappedPayload['label'] = $input->label;
        }
        if ($input->hasAmount) {
            $mappedPayload['amount'] = $input->amount;
        }
        if ($input->hasCurrency) {
            $mappedPayload['currency'] = $input->currency;
        }
        if ($input->hasStatus) {
            $mappedPayload['status'] = $input->status;
        }
        if ($input->hasDueAt) {
            $mappedPayload['dueAt'] = $input->dueAt;
        }
        if ($input->hasPaidAt) {
            $mappedPayload['paidAt'] = $input->paidAt;
        }

        $this->messageBus->dispatch(new PatchBillingCommand(
            applicationSlug: 'crm-general-core',
            billingId: $billing,
            payload: $mappedPayload,
        ));

        return new JsonResponse(new EntityIdResponseDto($billing)->toArray());
    }
}
