<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1;

use App\Blog\Domain\Enum\BlogReactionType;

use App\Blog\Application\Service\BlogReadService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
final readonly class BlogReadController
{
    public function __construct(
        private BlogReadService $blogReadService,
        private Security $security,
    ) {
    }

    #[Route('/v1/blogs/general', methods: [Request::METHOD_GET])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
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
                'isAuthor' => true,
                'content' => 'Fixture post 1 for General Blog Root',
                'author' => [
                    'username' => 'john.root',
                    'firstName' => 'John',
                    'lastName' => 'Root',
                    'photo' => 'https://...',
                ],
                'comments' => [[
                    'id' => '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e72',
                    'isAuthor' => false,
                    'content' => 'Parent comment #1',
                    'author' => [
                        'username' => 'john.admin',
                        'firstName' => 'John',
                        'lastName' => 'Admin',
                        'photo' => 'https://...',
                    ],
                    'reactions' => [[
                        'type' => 'like',
                        'authorId' => '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e73',
                        'isAuthor' => false,
                        'author' => [
                            'username' => 'john.user',
                            'firstName' => 'John',
                            'lastName' => 'User',
                            'photo' => 'https://...',
                        ],
                    ]],
                ]],
            ]],
        ]),
    )]
    public function general(User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->blogReadService->getGeneralBlogWithTree($loggedInUser));
    }

    #[Route('/v1/blogs/general/public', methods: [Request::METHOD_GET])]
    #[OA\Tag(name: 'Blog')]
    #[OA\Get(security: [])]
    #[OA\Response(
        response: 200,
        description: 'General blog public tree (isAuthor always false).',
        content: new OA\JsonContent(example: [
            'id' => '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e70',
            'title' => 'General Blog Root',
            'type' => 'general',
            'posts' => [[
                'id' => '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e71',
                'isAuthor' => false,
                'content' => 'Fixture post 1 for General Blog Root',
            ]],
        ]),
    )]
    public function generalPublic(): JsonResponse
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
            'tags' => [[
                'label' => 'tag-2-1',
            ]],
        ]),
    )]
    public function byApplication(string $applicationSlug): JsonResponse
    {
        $user = $this->security->getUser();

        return new JsonResponse($this->blogReadService->getByApplicationSlug($applicationSlug, $user instanceof User ? $user : null));
    }

    #[Route('/v1/blogs/reactions/types', methods: [Request::METHOD_GET])]
    #[OA\Tag(name: 'Blog')]
    #[OA\Get(security: [])]
    #[OA\Response(response: 200, description: 'Available reaction types.', content: new OA\JsonContent(example: [
        'items' => ['like', 'heart', 'laugh', 'celebrate'],
    ]))]
    public function reactionTypes(): JsonResponse
    {
        return new JsonResponse([
            'items' => BlogReactionType::values(),
        ]);
    }

}
