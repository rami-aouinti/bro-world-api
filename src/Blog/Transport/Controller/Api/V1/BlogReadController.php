<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1;

use App\Blog\Application\Service\BlogReadService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class BlogReadController
{
    public function __construct(private BlogReadService $blogReadService) {}

    #[Route('/v1/blogs/general', methods: [Request::METHOD_GET])]
    #[OA\Tag(name: 'Blog')]
    #[OA\Response(
        response: 200,
        description: 'General blog with nested posts, comments and reactions.',
        content: new OA\JsonContent(example: [
            'id' => '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e70',
            'title' => 'General Blog Root',
            'type' => 'general',
            'posts' => [[
                'id' => '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e71',
                'content' => 'Fixture post 1 for General Blog Root',
                'comments' => [[
                    'id' => '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e72',
                    'content' => 'Parent comment #1',
                    'reactions' => [['type' => 'like', 'authorId' => '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e73']],
                ]],
            ]],
        ]),
    )]
    public function general(): JsonResponse
    {
        return new JsonResponse($this->blogReadService->getGeneralBlogWithTree());
    }

    #[Route('/v1/blogs/application/{applicationSlug}', methods: [Request::METHOD_GET])]
    #[OA\Tag(name: 'Blog')]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'shop-ops-center')]
    #[OA\Response(
        response: 200,
        description: 'Application blog tree.',
        content: new OA\JsonContent(example: [
            'id' => '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e80',
            'title' => 'Shop Blog',
            'type' => 'application',
            'applicationSlug' => 'shop-ops-center',
            'posts' => [],
            'tags' => [['label' => 'tag-2-1']],
        ]),
    )]
    public function byApplication(string $applicationSlug): JsonResponse
    {
        return new JsonResponse($this->blogReadService->getByApplicationSlug($applicationSlug));
    }
}
