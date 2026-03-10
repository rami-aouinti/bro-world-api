<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1;

use App\General\Application\Message\EntityCreated;
use App\General\Application\Message\EntityDeleted;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use App\Shop\Application\Service\ProductListService;
use App\Shop\Domain\Entity\Category;
use App\Shop\Domain\Entity\Product;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Domain\Entity\Tag;
use App\Shop\Infrastructure\Repository\CategoryRepository;
use App\Shop\Infrastructure\Repository\ProductRepository;
use App\Shop\Infrastructure\Repository\ShopRepository;
use App\Shop\Infrastructure\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Shop')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class ShopController
{
    public function __construct(
        private ProductRepository $productRepository,
        private CategoryRepository $categoryRepository,
        private TagRepository $tagRepository,
        private ShopRepository $shopRepository,
        private ProductListService $productListService,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/shop/products', methods: [Request::METHOD_GET])]
    public function products(Request $request): JsonResponse
    {
        return new JsonResponse($this->productListService->getList($request));
    }

    #[Route('/v1/shop/products', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'POST /v1/shop/products', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['payload'], properties: [
        new OA\Property(property: 'payload', type: 'object', example: [
            'value' => 'example',
        ])], example: [
            'payload' => [
                'value' => 'example',
            ],
        ])), tags: ['Shop'], parameters: [], responses: [new OA\Response(response: 201, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    public function createProduct(Request $request): JsonResponse
    {
        $payload = (array)json_decode((string)$request->getContent(), true);

        $product = new Product();
        $product->setName((string)($payload['name'] ?? ''))->setPrice((float)($payload['price'] ?? 0));

        if (is_string($payload['shopId'] ?? null)) {
            $product->setShop($this->shopRepository->find($payload['shopId']));
        }
        if (is_string($payload['categoryId'] ?? null)) {
            $product->setCategory($this->categoryRepository->find($payload['categoryId']));
        }
        foreach ((array)($payload['tagIds'] ?? []) as $tagId) {
            if (is_string($tagId) && ($tag = $this->tagRepository->find($tagId)) instanceof Tag) {
                $product->addTag($tag);
            }
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_product', $product->getId()));

        return new JsonResponse([
            'id' => $product->getId(),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/v1/shop/products/{id}', methods: [Request::METHOD_DELETE])]
    public function deleteProduct(string $id): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product instanceof Product) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($product);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('shop_product', $id));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/shop/categories', methods: [Request::METHOD_GET])]
    public function categories(): JsonResponse
    {
        $items = array_map(static fn (Category $category): array => [
            'id' => $category->getId(),
            'name' => $category->getName(),
        ], $this->categoryRepository->findBy([], [
            'createdAt' => 'DESC',
        ], 200));

        return new JsonResponse([
            'items' => $items,
        ]);
    }

    #[Route('/v1/shop/categories', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'POST /v1/shop/categories', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['payload'], properties: [
        new OA\Property(property: 'payload', type: 'object', example: [
            'value' => 'example',
        ])], example: [
            'payload' => [
                'value' => 'example',
            ],
        ])), tags: ['Shop'], parameters: [], responses: [new OA\Response(response: 201, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    public function createCategory(Request $request): JsonResponse
    {
        $payload = (array)json_decode((string)$request->getContent(), true);
        $category = new Category();
        $category->setName((string)($payload['name'] ?? ''));
        if (is_string($payload['shopId'] ?? null)) {
            $category->setShop($this->shopRepository->find($payload['shopId']));
        }

        $this->entityManager->persist($category);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_category', $category->getId()));

        return new JsonResponse([
            'id' => $category->getId(),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/v1/shop/categories/{id}', methods: [Request::METHOD_DELETE])]
    public function deleteCategory(string $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);
        if (!$category instanceof Category) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('shop_category', $id));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/shop/tags', methods: [Request::METHOD_GET])]
    public function tags(): JsonResponse
    {
        $items = array_map(static fn (Tag $tag): array => [
            'id' => $tag->getId(),
            'label' => $tag->getLabel(),
        ], $this->tagRepository->findBy([], [
            'createdAt' => 'DESC',
        ], 200));

        return new JsonResponse([
            'items' => $items,
        ]);
    }

    #[Route('/v1/shop/tags', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'POST /v1/shop/tags', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['payload'], properties: [
        new OA\Property(property: 'payload', type: 'object', example: [
            'value' => 'example',
        ])], example: [
            'payload' => [
                'value' => 'example',
            ],
        ])), tags: ['Shop'], parameters: [], responses: [new OA\Response(response: 201, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    public function createTag(Request $request): JsonResponse
    {
        $payload = (array)json_decode((string)$request->getContent(), true);
        $tag = new Tag();
        $tag->setLabel((string)($payload['label'] ?? ''));
        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_tag', $tag->getId()));

        return new JsonResponse([
            'id' => $tag->getId(),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/v1/shop/tags/{id}', methods: [Request::METHOD_DELETE])]
    public function deleteTag(string $id): JsonResponse
    {
        $tag = $this->tagRepository->find($id);
        if (!$tag instanceof Tag) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($tag);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('shop_tag', $id));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/shop/applications/{applicationSlug}/products', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'shop-ops-center')]
    #[OA\Response(response: 200, description: 'Products list scoped to one application/shop.')]
    public function productsByApplication(string $applicationSlug, Request $request): JsonResponse
    {
        $shop = $this->resolveOrCreateShopByApplicationSlug($applicationSlug);
        $items = array_map(static fn (Product $product): array => [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'price' => $product->getPrice(),
            'categoryId' => $product->getCategory()?->getId(),
        ], $this->productRepository->findBy([
            'shop' => $shop,
        ], [
            'createdAt' => 'DESC',
        ], max(1, min(200, $request->query->getInt('limit', 50)))));

        return new JsonResponse([
            'applicationSlug' => $applicationSlug,
            'shopId' => $shop->getId(),
            'items' => $items,
        ]);
    }

    #[Route('/v1/shop/applications/{applicationSlug}/products', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'POST /v1/shop/applications/{applicationSlug}/products', tags: ['Shop'], parameters: [new OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 201, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'shop-ops-center')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'price'],
            properties: [
                new OA\Property(property: 'name', type: 'string', minLength: 1, example: 'Clavier mecanique'),
                new OA\Property(property: 'price', type: 'number', format: 'float', example: 129.9),
                new OA\Property(property: 'categoryId', type: 'string', format: 'uuid', example: null, nullable: true),
                new OA\Property(property: 'tagIds', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), example: []),
            ],
            type: 'object',
            example: [
                'name' => 'Clavier mecanique',
                'price' => 129.9,
                'categoryId' => null,
                'tagIds' => [],
            ],
        )
    )]
    public function createProductByApplication(string $applicationSlug, Request $request): JsonResponse
    {
        $shop = $this->resolveOrCreateShopByApplicationSlug($applicationSlug);
        $payload = (array)json_decode((string)$request->getContent(), true);

        $product = (new Product())
            ->setShop($shop)
            ->setName((string)($payload['name'] ?? ''))
            ->setPrice((float)($payload['price'] ?? 0));

        if (is_string($payload['categoryId'] ?? null)) {
            $category = $this->categoryRepository->find($payload['categoryId']);
            if ($category instanceof Category && $category->getShop()?->getId() === $shop->getId()) {
                $product->setCategory($category);
            }
        }

        foreach ((array)($payload['tagIds'] ?? []) as $tagId) {
            if (is_string($tagId) && ($tag = $this->tagRepository->find($tagId)) instanceof Tag) {
                $product->addTag($tag);
            }
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_product', $product->getId(), context: [
            'applicationSlug' => $applicationSlug,
        ]));

        return new JsonResponse([
            'id' => $product->getId(),
            'shopId' => $shop->getId(),
            'applicationSlug' => $applicationSlug,
        ], JsonResponse::HTTP_CREATED);
    }

    private function resolveOrCreateShopByApplicationSlug(string $applicationSlug): Shop
    {
        $shop = $this->shopRepository->findOneByApplicationSlug($applicationSlug);
        if ($shop instanceof Shop) {
            return $shop;
        }

        $application = $this->entityManager->getRepository(Application::class)->findOneBy([
            'slug' => $applicationSlug,
        ]);
        if (!$application instanceof Application || $application->getPlatform()?->getPlatformKey() !== PlatformKey::SHOP) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown "applicationSlug" for Shop platform.');
        }

        $shop = (new Shop())
            ->setApplication($application)
            ->setName($application->getTitle() . ' Catalog');

        $this->entityManager->persist($shop);
        $this->entityManager->flush();

        return $shop;
    }
}
