<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\Traits\Methods\IdsMethod;
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
#[OA\Tag(name: 'Recruit Management')]
class JobIdsController extends Controller
{
    use IdsMethod;

    public function __construct(
        JobResource $resource,
    ) {
        parent::__construct($resource);
    }

    #[Route(
        path: '/v1/recruit/job/ids',
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ROOT')]
    #[OA\Get(summary: 'Ids job', responses: [new OA\Response(response: 200, description: 'success')])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request): Response
    {
        $request->attributes->set('applicationSlug', $applicationSlug);

        return $this->idsMethod($request);
    }
}
