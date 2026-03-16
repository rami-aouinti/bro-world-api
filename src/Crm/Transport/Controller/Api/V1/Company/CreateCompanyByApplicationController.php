<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Company;

use App\Crm\Application\Message\CreateCompanyCommand;
use App\Crm\Transport\Request\CreateCompanyRequest;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Crm\Transport\Request\CrmRequestHandler;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_OWNER->value)]
final readonly class CreateCompanyByApplicationController
{
    public function __construct(
        private CrmRequestHandler $crmRequestHandler,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/companies', methods: [Request::METHOD_POST])]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateCompanyRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $id = \Ramsey\Uuid\Uuid::uuid4()->toString();

        $this->messageBus->dispatch(new CreateCompanyCommand(
            id: $id,
            applicationSlug: $applicationSlug,
            name: (string)$input->name,
            industry: $input->industry,
            website: $input->website,
            contactEmail: $input->contactEmail,
            phone: $input->phone,
        ));

        return new JsonResponse([
            'id' => $id,
            'applicationSlug' => $applicationSlug,
        ], JsonResponse::HTTP_CREATED);
    }
}
