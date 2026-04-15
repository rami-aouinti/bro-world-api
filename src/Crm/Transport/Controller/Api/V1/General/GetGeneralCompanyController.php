<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Application\Service\CompanyApplicationListService;
use App\Crm\Domain\Entity\Company;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
final readonly class GetGeneralCompanyController
{
    public function __construct(private CompanyApplicationListService $companyApplicationListService)
    {
    }

    #[Route('/v1/crm/general/companies/{company}', methods: [Request::METHOD_GET])]
    public function __invoke(Company $company): JsonResponse
    {
        return new JsonResponse($this->companyApplicationListService->getGlobalDetail($company));
    }
}
