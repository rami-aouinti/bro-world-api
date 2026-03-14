<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Billing;

use App\Crm\Application\Service\BillingReadService;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class ListBillingsController
{
    public function __construct(private BillingReadService $billingReadService)
    {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/billings', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        return new JsonResponse($this->billingReadService->getList($applicationSlug, $request));
    }
}
