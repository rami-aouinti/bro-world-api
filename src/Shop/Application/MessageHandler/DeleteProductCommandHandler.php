<?php

declare(strict_types=1);

namespace App\Shop\Application\MessageHandler;

use App\General\Application\Message\EntityDeleted;
use App\Shop\Application\Message\DeleteProductCommand;
use App\Shop\Domain\Entity\Product;
use App\Shop\Infrastructure\Repository\ProductRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class DeleteProductCommandHandler
{
    public function __construct(
        private ProductRepository $productRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(DeleteProductCommand $command): void
    {
        $entityManager = $this->productRepository->getEntityManager();

        $entityManager->getConnection()->transactional(function () use ($command): void {
            $product = $this->productRepository->find($command->productId);
            if (!$product instanceof Product) {
                return;
            }

            $this->productRepository->remove($product);
        });

        $this->messageBus->dispatch(new EntityDeleted('shop_product', $command->productId, context: [
            'operationId' => $command->operationId,
        ]));
    }
}
