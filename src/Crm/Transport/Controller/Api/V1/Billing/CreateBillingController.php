<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Billing;

use App\Crm\Application\Dto\Command\CreateBillingCommandDto;
use App\Crm\Application\Dto\Response\EntityIdResponseDto;
use App\Crm\Application\Message\CreateBillingCommand;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Billing;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_OWNER->value)]
final readonly class CreateBillingController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CompanyRepository $companyRepository,
        private CrmRequestHandler $crmRequestHandler,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/v1/crm/billings', methods: [Request::METHOD_POST])]
        #[OA\Post(
        description: 'Exécute l action metier Create Billing dans le perimetre de l application CRM.',
        summary: 'Create Billing',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_CREATED, description: 'Ressource créée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateBillingCommandDto::class, mapperMethod: 'fromPostArray');
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $company = $this->companyRepository->findOneScopedById((string)$input->companyId, $crm->getId());
        if ($company === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Company not found for this CRM scope.');
        }

        $billing = new Billing()
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

        return new JsonResponse(new EntityIdResponseDto($billing->getId(), [
            'companyId' => $company->getId(),
        ])->toArray(), JsonResponse::HTTP_CREATED);
    }
}
