<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Company;

use App\General\Transport\Rest\Controller;
use App\Recruit\Application\Resource\CompanyResource;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Recruit Company Management')]
class CompanyDeleteController extends Controller
{
    public function __construct(
        CompanyResource $resource,
    ) {
        parent::__construct($resource);
    }

    #[Route(
        path: '/v1/recruit/company/{id}',
        requirements: ['id' => Requirement::UUID_V1],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_ROOT')]
    #[OA\Delete(summary: 'Delete company', responses: [new OA\Response(response: 200, description: 'deleted')])]
    public function __invoke(Request $request, string $id): Response
    {
        return $this->deleteMethod($request, $id);
    }
}
