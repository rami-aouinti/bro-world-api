<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Category;

use App\General\Application\Message\EntityCreated;
use App\Shop\Application\Service\ShopApplicationResolverService;
use App\Shop\Application\Service\SlugBuilderService;
use App\Shop\Domain\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Shop')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class CreateCategoryController
{
    public function __construct(
        private ShopApplicationResolverService $shopApplicationResolverService,
        private SlugBuilderService $slugBuilderService,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/shop/applications/{applicationSlug}/categories', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $shop = $this->shopApplicationResolverService->resolveOrCreateShopByApplicationSlug($applicationSlug);
        $payload = (array)json_decode((string)$request->getContent(), true);
        $category = (new Category())
            ->setShop($shop)
            ->setName((string)($payload['name'] ?? ''))
            ->setSlug($this->slugBuilderService->buildSlug((string)($payload['slug'] ?? $payload['name'] ?? '')))
            ->setDescription(($payload['description'] ?? null) !== null ? (string)$payload['description'] : null);

        $this->entityManager->persist($category);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_category', $category->getId()));

        return new JsonResponse([
            'id' => $category->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
