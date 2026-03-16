<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Company;

use App\Crm\Application\Exception\CrmReferenceNotFoundException;
use App\Crm\Application\Message\DeleteCompanyCommand;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class DeleteCompanyController
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private CrmApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/companies/{company}', methods: [Request::METHOD_DELETE])]
    public function __invoke(string $applicationSlug, string $company): JsonResponse
    {
        try {
            $this->messageBus->dispatch(new DeleteCompanyCommand(
                applicationSlug: $applicationSlug,
                companyId: $company,
            ));
        } catch (CrmReferenceNotFoundException $exception) {
            return $this->errorResponseFactory->notFoundReference($exception->field);
        }

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
