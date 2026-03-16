<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Company;

use App\Crm\Application\Exception\CrmReferenceNotFoundException;
use App\Crm\Application\Message\PutCompanyCommand;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Crm\Transport\Request\UpdateCompanyRequest;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Crm\Transport\Request\CrmRequestHandler;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PutCompanyController
{
    public function __construct(
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmRequestHandler $crmRequestHandler,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/companies/{companyId}', methods: [Request::METHOD_PUT])]
    public function __invoke(string $applicationSlug, string $companyId, Request $request): JsonResponse
    {
        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, UpdateCompanyRequest::class, ['Default', 'put'], 'fromPutArray');
        if ($input instanceof JsonResponse) {
            return $input;
        }

        try {
            $this->messageBus->dispatch(new PutCompanyCommand(
                applicationSlug: $applicationSlug,
                companyId: $companyId,
                name: (string)$input->name,
                industry: $input->industry,
                website: $input->website,
                contactEmail: $input->contactEmail,
                phone: $input->phone,
            ));
        } catch (CrmReferenceNotFoundException $exception) {
            return $this->errorResponseFactory->notFoundReference($exception->field);
        }

        return new JsonResponse(['id' => $companyId]);
    }
}
