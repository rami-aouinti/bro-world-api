<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Tag;

use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\Traits\Methods\CountMethod;
use App\Recruit\Application\Resource\TagResource;
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
class TagCountController extends Controller
{
    use CountMethod;

    public function __construct(
        TagResource $resource,
    ) {
        parent::__construct($resource);
    }

    #[Route(
        path: '/v1/recruit/tag/count',
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted('ROLE_ROOT')]
    #[OA\Get(summary: 'Count tag', responses: [new OA\Response(response: 200, description: 'success')])]
        public function __invoke(string $applicationSlug, Request $request): Response
    {
        $request->attributes->set('applicationSlug', $applicationSlug);

        return $this->countMethod($request);
    }
}
