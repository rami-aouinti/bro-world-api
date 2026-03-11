<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shop\Application\MessageHandler;

use App\Shop\Application\Message\CheckoutCommand;
use App\Shop\Application\MessageHandler\CheckoutCommandHandler;
use App\Shop\Application\Service\CheckoutService;
use App\Shop\Domain\Entity\Order;
use App\Shop\Infrastructure\Repository\OrderRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

final class CheckoutCommandHandlerTest extends TestCase
{
    public function testInvokeExecutesCheckoutInTransaction(): void
    {
        $command = new CheckoutCommand('op', 'shop-id', 'user-id', 'a', 'b', 'c@d.test', '123', 'pickup');
        $order = new Order();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('getEntityManager')->willReturn($entityManager);

        $checkoutService = $this->createMock(CheckoutService::class);
        $checkoutService->expects(self::once())->method('checkout')->with($command)->willReturn($order);

        $handler = new CheckoutCommandHandler($checkoutService, $orderRepository);

        self::assertSame($order, $handler($command));
    }
}
