<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\General\Transport\Rest\Controller;
use App\Recruit\Application\Resource\JobResource;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Recruit Job Management')]
class JobCountController extends Controller
{
    public function __construct(
        JobResource $resource,
    ) {
        parent::__construct($resource);
    }

    #[Route(
        path: '/v1/recruit/job/count',
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ROOT')]
    #[OA\Get(summary: 'Count job', responses: [new OA\Response(response: 200, description: 'success')])]
    public function __invoke(Request $request): Response
    {
        return $this->countMethod($request);
    }
}
