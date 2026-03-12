<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1\Read;

use App\Blog\Application\Service\BlogReadService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Blog')]
final readonly class GetBlogPostBySlugController
{
    public function __construct(
        private BlogReadService $blogReadService,
        private Security $security,
    ) {
    }

    #[Route('/v1/blog/posts/{slug}', methods: [Request::METHOD_GET])]
    public function __invoke(string $slug): JsonResponse
    {
        $user = $this->security->getUser();

        return new JsonResponse($this->blogReadService->getPostBySlug($slug, $user instanceof User ? $user : null));
    }
}
