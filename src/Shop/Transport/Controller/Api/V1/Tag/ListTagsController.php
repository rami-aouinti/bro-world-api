<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Tag;

use App\Shop\Application\Service\ShopApplicationResolverService;
use App\Shop\Domain\Entity\Tag;
use App\Shop\Infrastructure\Repository\TagRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Shop')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class ListTagsController
{
    public function __construct(
        private ShopApplicationResolverService $shopApplicationResolverService,
        private TagRepository $tagRepository,
    ) {
    }

    #[Route('/v1/shop/tags', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug): JsonResponse
    {
        $this->shopApplicationResolverService->resolveOrCreateShopByApplicationSlug($applicationSlug);

        $items = array_map(static fn (Tag $tag): array => [
            'id' => $tag->getId(),
            'label' => $tag->getLabel(),
            'type' => $tag->getType()->value,
        ], $this->tagRepository->findByApplicationScope($applicationSlug));

        return new JsonResponse([
            'items' => $items,
        ]);
    }
}
