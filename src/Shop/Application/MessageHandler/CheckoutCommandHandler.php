<?php

declare(strict_types=1);

namespace App\Shop\Application\MessageHandler;

use App\Shop\Application\Message\CheckoutCommand;
use App\Shop\Application\Service\CheckoutService;
use App\Shop\Domain\Entity\Order;
use App\Shop\Infrastructure\Repository\OrderRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CheckoutCommandHandler
{
    public function __construct(
        private CheckoutService $checkoutService,
        private OrderRepository $orderRepository,
    ) {
    }

    public function __invoke(CheckoutCommand $command): Order
    {
        $entityManager = $this->orderRepository->getEntityManager();

        /** @var Order $order */
        $order = $entityManager->getConnection()->transactional(fn (): Order => $this->checkoutService->checkout($command));

        return $order;
    }
}

