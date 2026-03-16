<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Billing;

use App\Crm\Application\Message\DeleteBillingCommand;
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
final readonly class DeleteBillingController
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/billings/{billing}', methods: [Request::METHOD_DELETE])]
    public function __invoke(string $applicationSlug, string $billing): JsonResponse
    {
        $this->messageBus->dispatch(new DeleteBillingCommand(
            applicationSlug: $applicationSlug,
            billingId: $billing,
        ));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
