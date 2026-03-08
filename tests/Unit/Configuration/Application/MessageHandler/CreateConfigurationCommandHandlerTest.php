<?php

declare(strict_types=1);

namespace App\Tests\Unit\Configuration\Application\MessageHandler;

use App\Configuration\Application\DTO\Configuration\ConfigurationCreate;
use App\Configuration\Application\Message\CreateConfigurationCommand;
use App\Configuration\Application\MessageHandler\CreateConfigurationCommandHandler;
use App\Configuration\Application\Resource\ConfigurationResource;
use App\Configuration\Infrastructure\Repository\ConfigurationRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

final class CreateConfigurationCommandHandlerTest extends TestCase
{
    public function testInvokeDelegatesToResourceInsideTransaction(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback());

        $entityManager = $this->createMock(EntityManager::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $repository = $this->createMock(ConfigurationRepository::class);
        $repository->method('getEntityManager')->willReturn($entityManager);

        $resource = $this->createMock(ConfigurationResource::class);
        $resource->expects(self::once())->method('create');

        $handler = new CreateConfigurationCommandHandler($resource, $repository);
        $handler(new CreateConfigurationCommand('operation-id', new ConfigurationCreate()));
    }
}
