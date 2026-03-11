<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Tag;

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
        private TagRepository $tagRepository
    ) {
    }

    #[Route('/v1/shop/{applicationSlug}/tags', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug): JsonResponse
    {
        $items = array_map(static fn (Tag $tag): array => [
            'id' => $tag->getId(),
            'label' => $tag->getLabel(),
            'type' => $tag->getType()->value,
        ], $this->tagRepository->findBy([], [
            'createdAt' => 'DESC',
        ], 200));

        return new JsonResponse([
            'items' => $items,
        ]);
    }
}
