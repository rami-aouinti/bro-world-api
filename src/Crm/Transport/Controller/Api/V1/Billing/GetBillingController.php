<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Billing;

use App\Crm\Application\Service\BillingReadService;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class GetBillingController
{
    public function __construct(
        private BillingReadService $billingReadService
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/billings/{billing}', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, string $billing): JsonResponse
    {
        $payload = $this->billingReadService->getDetail($applicationSlug, $billing);
        if ($payload === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Billing not found for this CRM scope.');
        }

        return new JsonResponse($payload);
    }
}
