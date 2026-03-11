<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1;

use App\General\Application\Message\EntityCreated;
use App\General\Application\Message\EntityDeleted;
use App\General\Domain\Service\Interfaces\MessageServiceInterface;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use App\Shop\Application\Message\CreateProductCommand;
use App\Shop\Application\Message\DeleteProductCommand;
use App\Shop\Application\Service\ProductApplicationListService;
use App\Shop\Application\Service\ProductListService;
use App\Shop\Domain\Entity\Category;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Domain\Entity\Tag;
use App\Shop\Domain\Enum\ProductStatus;
use App\Shop\Domain\Enum\TagType;
use App\Shop\Infrastructure\Repository\CategoryRepository;
use App\Shop\Infrastructure\Repository\ProductRepository;
use App\Shop\Infrastructure\Repository\ShopRepository;
use App\Shop\Infrastructure\Repository\TagRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\SecurityBundle\Security;
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
        private ProductApplicationListService $productApplicationListService,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private MessageServiceInterface $messageService,
        private Security $security,
    ) {
    }

    #[Route('/v1/shop/products', methods: [Request::METHOD_GET])]
    public function products(Request $request): JsonResponse
    {
        return new JsonResponse($this->productListService->getList($request));
    }

    #[Route('/v1/shop/products/{id}', methods: [Request::METHOD_GET])]
    public function product(string $id): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product instanceof Product) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse(ProductListService::serializeProduct($product));
    }

    #[Route('/v1/shop/products', methods: [Request::METHOD_POST])]
    public function createProduct(Request $request): JsonResponse
    {
        $payload = (array)json_decode((string)$request->getContent(), true);
        $product = $this->hydrateProduct(new Product(), $payload);

        if (is_string($payload['shopId'] ?? null)) {
            $product->setShop($this->shopRepository->find($payload['shopId']));
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_product', $product->getId()));

        return new JsonResponse(['id' => $product->getId()], JsonResponse::HTTP_CREATED);
    }

    #[Route('/v1/shop/products/{id}', methods: [Request::METHOD_PATCH])]
    public function patchProduct(string $id, Request $request): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (!$product instanceof Product) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = (array)json_decode((string)$request->getContent(), true);
        $this->hydrateProduct($product, $payload, true);

        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_product', $product->getId()));

        return new JsonResponse(ProductListService::serializeProduct($product));
    }

    #[Route('/v1/shop/products/{id}', methods: [Request::METHOD_DELETE])]
    public function deleteProduct(string $id): JsonResponse
    {
        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageService->sendMessage(new DeleteProductCommand(
            operationId: $operationId,
            productId: $id,
        ));

        return new JsonResponse([
            'operationId' => $operationId,
            'id' => $id,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route('/v1/shop/categories', methods: [Request::METHOD_GET])]
    public function categories(): JsonResponse
    {
        $items = array_map(static fn (Category $category): array => [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'description' => $category->getDescription(),
        ], $this->categoryRepository->findBy([], ['createdAt' => 'DESC'], 200));

        return new JsonResponse(['items' => $items]);
    }

    #[Route('/v1/shop/categories', methods: [Request::METHOD_POST])]
    public function createCategory(Request $request): JsonResponse
    {
        $payload = (array)json_decode((string)$request->getContent(), true);
        $category = (new Category())
            ->setName((string)($payload['name'] ?? ''))
            ->setSlug($this->buildSlug((string)($payload['slug'] ?? $payload['name'] ?? '')))
            ->setDescription(($payload['description'] ?? null) !== null ? (string)$payload['description'] : null);

        if (is_string($payload['shopId'] ?? null)) {
            $category->setShop($this->shopRepository->find($payload['shopId']));
        }

        $this->entityManager->persist($category);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_category', $category->getId()));

        return new JsonResponse(['id' => $category->getId()], JsonResponse::HTTP_CREATED);
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
            'type' => $tag->getType()->value,
        ], $this->tagRepository->findBy([], ['createdAt' => 'DESC'], 200));

        return new JsonResponse(['items' => $items]);
    }

    #[Route('/v1/shop/tags', methods: [Request::METHOD_POST])]
    public function createTag(Request $request): JsonResponse
    {
        $payload = (array)json_decode((string)$request->getContent(), true);
        $tag = (new Tag())
            ->setLabel((string)($payload['label'] ?? ''))
            ->setType(TagType::tryFrom((string)($payload['type'] ?? '')) ?? TagType::MARKETING);

        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_tag', $tag->getId()));

        return new JsonResponse(['id' => $tag->getId()], JsonResponse::HTTP_CREATED);
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
    public function productsByApplication(string $applicationSlug, Request $request): JsonResponse
    {
        $shop = $this->resolveOrCreateShopByApplicationSlug($applicationSlug);

        return new JsonResponse($this->productApplicationListService->getList($request, $applicationSlug, $shop));
    }

    #[Route('/v1/shop/applications/{applicationSlug}/products', methods: [Request::METHOD_POST])]
    public function createProductByApplication(string $applicationSlug, Request $request): JsonResponse
    {
        $shop = $this->resolveOrCreateShopByApplicationSlug($applicationSlug);
        $payload = (array)json_decode((string)$request->getContent(), true);

        $product = $this->hydrateProduct((new Product())->setShop($shop), $payload);

        $this->entityManager->persist($product);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_product', $product->getId(), context: [
            'applicationSlug' => $applicationSlug,
        ]));

        return new JsonResponse([
            'operationId' => $operationId,
            'shopId' => $shop->getId(),
            'applicationSlug' => $applicationSlug,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    /** @param array<string,mixed> $payload */
    private function hydrateProduct(Product $product, array $payload, bool $partial = false): Product
    {
        if (!$partial || array_key_exists('name', $payload)) {
            $product->setName((string)($payload['name'] ?? ''));
        }
        if (!$partial || array_key_exists('price', $payload)) {
            $product->setPrice((float)($payload['price'] ?? 0));
        }
        if (!$partial || array_key_exists('sku', $payload)) {
            $defaultSku = strtoupper(substr(md5($product->getId()), 0, 12));
            $product->setSku((string)($payload['sku'] ?? $defaultSku));
        }
        if (!$partial || array_key_exists('description', $payload)) {
            $product->setDescription(($payload['description'] ?? null) !== null ? (string)$payload['description'] : null);
        }
        if (!$partial || array_key_exists('currencyCode', $payload)) {
            $product->setCurrencyCode((string)($payload['currencyCode'] ?? 'EUR'));
        }
        if (!$partial || array_key_exists('stock', $payload)) {
            $product->setStock((int)($payload['stock'] ?? 0));
        }
        if (!$partial || array_key_exists('isFeatured', $payload)) {
            $product->setIsFeatured((bool)($payload['isFeatured'] ?? false));
        }
        if (!$partial || array_key_exists('status', $payload)) {
            $status = ProductStatus::tryFrom((string)($payload['status'] ?? '')) ?? ProductStatus::DRAFT;
            $product->setStatus($status);
        }

        if (!$partial || array_key_exists('categoryId', $payload)) {
            $category = null;
            if (is_string($payload['categoryId'] ?? null)) {
                $category = $this->categoryRepository->find($payload['categoryId']);
                if ($category instanceof Category && $product->getShop() instanceof Shop && $category->getShop()?->getId() !== $product->getShop()?->getId()) {
                    $category = null;
                }
            }
            $product->setCategory($category instanceof Category ? $category : null);
        }

        if (!$partial || array_key_exists('tagIds', $payload)) {
            foreach ($product->getTags()->toArray() as $tag) {
                $product->removeTag($tag);
            }
            foreach ((array)($payload['tagIds'] ?? []) as $tagId) {
                if (is_string($tagId) && ($tag = $this->tagRepository->find($tagId)) instanceof Tag) {
                    $product->addTag($tag);
                }
            }
        }

        return $product;
    }

    private function buildSlug(string $value): string
    {
        return trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-');
    }

    private function resolveOrCreateShopByApplicationSlug(string $applicationSlug): Shop
    {
        $shop = $this->shopRepository->findOneByApplicationSlug($applicationSlug);
        if ($shop instanceof Shop) {
            $this->assertApplicationAccess($shop->getApplication(), PlatformKey::SHOP);

            return $shop;
        }

        $application = $this->entityManager->getRepository(Application::class)->findOneBy([
            'slug' => $applicationSlug,
        ]);
        if (!$application instanceof Application) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Unknown "applicationSlug".');
        }

        $this->assertApplicationAccess($application, PlatformKey::SHOP);

        throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Shop root entity not found for this application.');
    }

    private function assertApplicationAccess(?Application $application, PlatformKey $platformKey): void
    {
        if (!$application instanceof Application || $application->getPlatform()?->getPlatformKey() !== $platformKey) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid "applicationSlug" for the requested platform.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $application->getUser()?->getId() !== $user->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot access this application scope.');
        }
    }
}
