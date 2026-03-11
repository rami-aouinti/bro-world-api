<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1;

use App\Crm\Application\Service\CompanyApplicationListService;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class ListCompaniesByApplicationController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CompanyApplicationListService $companyApplicationListService,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/companies', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'crm-sales-hub')]
    #[OA\Response(response: 200, description: 'Companies list scoped to CRM application.')]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);

        return new JsonResponse($this->companyApplicationListService->getList($request, $applicationSlug, $crm));
    }
}
