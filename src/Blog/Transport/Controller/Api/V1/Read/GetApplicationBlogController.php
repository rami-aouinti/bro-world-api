<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1\Read;

use App\Blog\Application\Service\BlogReadService;
use App\General\Application\Service\ApplicationScopeResolver;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Blog')]
final readonly class GetApplicationBlogController
{
    public function __construct(
        private BlogReadService $blogReadService,
        private Security $security,
        private ApplicationScopeResolver $applicationScopeResolver,
    ) {
    }

    #[Route('/v1/blog/feed', methods: [Request::METHOD_GET])]
    #[OA\Get(
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1)),
            new OA\Parameter(name: 'tag', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $applicationSlug = $this->applicationScopeResolver->resolveFromRequest($request);
        $user = $this->security->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $tag = trim((string)$request->query->get('tag', ''));

        return new JsonResponse($this->blogReadService->getByApplicationSlug(
            $applicationSlug,
            $user instanceof User ? $user : null,
            $page,
            $limit,
            $tag !== '' ? $tag : null,
        ));
    }
}
