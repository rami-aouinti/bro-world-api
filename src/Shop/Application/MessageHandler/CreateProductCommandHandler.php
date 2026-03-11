<?php

declare(strict_types=1);

namespace App\Shop\Application\MessageHandler;

use App\General\Application\Message\EntityCreated;
use App\Shop\Application\Message\CreateProductCommand;
use App\Shop\Domain\Entity\Category;
use App\Shop\Domain\Entity\Product;
use App\Shop\Domain\Entity\Tag;
use App\Shop\Infrastructure\Repository\CategoryRepository;
use App\Shop\Infrastructure\Repository\ProductRepository;
use App\Shop\Infrastructure\Repository\ShopRepository;
use App\Shop\Infrastructure\Repository\TagRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CreateProductCommandHandler
{
    public function __construct(
        private ProductRepository $productRepository,
        private ShopRepository $shopRepository,
        private CategoryRepository $categoryRepository,
        private TagRepository $tagRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(CreateProductCommand $command): void
    {
        $entityManager = $this->productRepository->getEntityManager();

        $product = $entityManager->getConnection()->transactional(function () use ($command): Product {
            $product = (new Product())
                ->setName($command->name)
                ->setPrice($command->price);

            if ($command->shopId !== null) {
                $shop = $this->shopRepository->find($command->shopId);
                if ($shop === null) {
                    throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Shop not found.');
                }

                $product->setShop($shop);
            }

            if ($command->categoryId !== null) {
                $category = $this->categoryRepository->find($command->categoryId);
                if (!$category instanceof Category) {
                    throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Category not found.');
                }

                if ($command->shopId !== null && $category->getShop()?->getId() !== $command->shopId) {
                    throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Category does not belong to this shop.');
                }

                $product->setCategory($category);
            }

            foreach ($command->tagIds as $tagId) {
                $tag = $this->tagRepository->find($tagId);
                if ($tag instanceof Tag) {
                    $product->addTag($tag);
                }
            }

            $this->productRepository->save($product);

            return $product;
        });

        $this->messageBus->dispatch(new EntityCreated('shop_product', $product->getId(), context: [
            'operationId' => $command->operationId,
            'applicationSlug' => $command->applicationSlug,
        ]));
    }
}
