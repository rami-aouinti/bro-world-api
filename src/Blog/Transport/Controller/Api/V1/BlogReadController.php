<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1;

use App\Blog\Application\Service\BlogReadService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class BlogReadController
{
    public function __construct(private BlogReadService $blogReadService) {}

    #[Route('/v1/blogs/general', methods: [Request::METHOD_GET])]
    public function general(): JsonResponse
    {
        return new JsonResponse($this->blogReadService->getGeneralBlogWithTree());
    }

    #[Route('/v1/blogs/application/{applicationSlug}', methods: [Request::METHOD_GET])]
    public function byApplication(string $applicationSlug): JsonResponse
    {
        return new JsonResponse($this->blogReadService->getByApplicationSlug($applicationSlug));
    }
}
