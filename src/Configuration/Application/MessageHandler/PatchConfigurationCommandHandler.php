<?php

declare(strict_types=1);

namespace App\Configuration\Application\MessageHandler;

use App\Configuration\Application\Message\PatchConfigurationCommand;
use App\Configuration\Application\Resource\ConfigurationResource;
use App\Configuration\Infrastructure\Repository\ConfigurationRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PatchConfigurationCommandHandler
{
    public function __construct(
        private ConfigurationResource $resource,
        private ConfigurationRepository $repository,
    ) {
    }

    public function __invoke(PatchConfigurationCommand $command): void
    {
        $entityManager = $this->repository->getEntityManager();
        $entityManager->getConnection()->transactional(function () use ($command): void {
            $this->resource->patch($command->id, $command->dto, true);
        });
    }
}
