<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command\Elastic;

use App\Crm\Application\Projection\CrmRepositoryProjection;
use App\Crm\Domain\Entity\CrmRepository;
use App\Crm\Infrastructure\Repository\CrmProjectRepositoryRepository;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Index CRM repositories in Elasticsearch.',
)]
final class ReindexCrmRepositoriesCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'elastic:reindex:crm-repositories';

    public function __construct(
        private readonly CrmProjectRepositoryRepository $crmRepository,
        private readonly ElasticsearchServiceInterface $elasticsearchService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexed = 0;

        /** @var CrmRepository $repository */
        foreach ($this->crmRepository->findBy([], ['createdAt' => 'DESC']) as $repository) {
            $this->elasticsearchService->index(CrmRepositoryProjection::INDEX_NAME, $repository->getId(), [
                'id' => $repository->getId(),
                'projectId' => $repository->getProject()?->getId(),
                'projectName' => $repository->getProject()?->getName(),
                'provider' => $repository->getProvider(),
                'owner' => $repository->getOwner(),
                'name' => $repository->getName(),
                'fullName' => $repository->getFullName(),
                'defaultBranch' => $repository->getDefaultBranch(),
                'isPrivate' => $repository->isPrivate(),
                'htmlUrl' => $repository->getHtmlUrl(),
                'externalId' => $repository->getExternalId(),
                'syncStatus' => $repository->getSyncStatus(),
                'lastSyncedAt' => $repository->getLastSyncedAt()?->format(DATE_ATOM),
                'updatedAt' => $repository->getUpdatedAt()?->format(DATE_ATOM),
            ]);
            $indexed++;
        }

        if ($input->isInteractive()) {
            $this->getSymfonyStyle($input, $output)->success('CRM repositories indexed: ' . $indexed);
        }

        return Command::SUCCESS;
    }
}
