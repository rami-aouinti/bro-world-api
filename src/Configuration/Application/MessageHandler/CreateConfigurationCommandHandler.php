<?php

declare(strict_types=1);

namespace App\Configuration\Application\MessageHandler;

use App\Configuration\Application\Message\CreateConfigurationCommand;
use App\Configuration\Application\Resource\ConfigurationResource;
use App\Configuration\Infrastructure\Repository\ConfigurationRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final readonly class CreateConfigurationCommandHandler
{
    public function __construct(
        private ConfigurationResource $resource,
        private ConfigurationRepository $repository,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function __invoke(CreateConfigurationCommand $command): void
    {
        $entityManager = $this->repository->getEntityManager();
        $entityManager->getConnection()->transactional(function () use ($command): void {
            $this->resource->create($command->dto, true);
        });
    }
}
