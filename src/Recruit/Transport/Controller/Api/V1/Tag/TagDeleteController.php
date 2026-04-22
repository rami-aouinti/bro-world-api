<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Tag;

use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\Traits\Methods\DeleteMethod;
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
#[OA\Tag(name: 'Recruit Management')]
class TagDeleteController extends Controller
{
    use DeleteMethod;

    public function __construct(
        TagResource $resource,
    ) {
        parent::__construct($resource);
    }

    #[Route(
        path: '/v1/recruit/tag/{id}',
        requirements: [
            'id' => Requirement::UUID_V1,
        ],
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted('ROLE_ROOT')]
    #[OA\Delete(summary: 'Delete tag', responses: [new OA\Response(response: 200, description: 'deleted')])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request, string $id): Response
    {
        $request->attributes->set('applicationSlug', $applicationSlug);

        return $this->deleteMethod($request, $id);
    }
}
