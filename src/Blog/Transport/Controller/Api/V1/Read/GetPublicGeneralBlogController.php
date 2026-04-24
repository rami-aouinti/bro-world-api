<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1\Read;

use App\Blog\Application\Service\BlogReadService;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Blog')]
final readonly class GetPublicGeneralBlogController
{
    public function __construct(
        private BlogReadService $blogReadService
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/v1/public/blogs/general', methods: [Request::METHOD_GET])]
    #[OA\Get(
        security: [],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'tag', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $tag = trim((string)$request->query->get('tag', ''));

        return new JsonResponse($this->blogReadService->getGeneralBlogWithTree(null, $page, $limit, $tag !== '' ? $tag : null));
    }
}
