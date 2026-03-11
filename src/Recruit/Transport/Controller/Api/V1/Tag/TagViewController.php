<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Tag;

use App\General\Transport\Rest\Controller;
use App\Recruit\Application\Resource\TagResource;
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
#[OA\Tag(name: 'Recruit Tag Management')]
class TagViewController extends Controller
{
    public function __construct(
        TagResource $resource,
    ) {
        parent::__construct($resource);
    }

    #[Route(
        path: '/v1/recruit/tag/{id}',
        requirements: ['id' => Requirement::UUID_V1],
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ROOT')]
    #[OA\Get(summary: 'View tag', responses: [new OA\Response(response: 200, description: 'success')])]
    public function __invoke(Request $request, string $id): Response
    {
        return $this->findOneMethod($request, $id);
    }
}
