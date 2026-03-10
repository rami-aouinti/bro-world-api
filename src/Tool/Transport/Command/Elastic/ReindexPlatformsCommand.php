<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command\Elastic;

use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use App\Platform\Application\Projection\ApplicationProjection;
use App\Platform\Domain\Entity\Application;
use App\Platform\Infrastructure\Repository\ApplicationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: 'Index platform applications in Elasticsearch.',
)]
final class ReindexPlatformsCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'elastic:reindex:platforms';

    public function __construct(
        private readonly ApplicationRepository $applicationRepository,
        private readonly ElasticsearchServiceInterface $elasticsearchService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexed = 0;

        /** @var Application $application */
        foreach ($this->applicationRepository->findBy([], [
            'createdAt' => 'DESC',
        ]) as $application) {
            $this->elasticsearchService->index(ApplicationProjection::INDEX_NAME, $application->getId(), [
                'id' => $application->getId(),
                'title' => $application->getTitle(),
                'description' => $application->getDescription(),
                'slug' => $application->getSlug(),
                'platformName' => $application->getPlatform()?->getName() ?? '',
                'platformKey' => $application->getPlatform()?->getPlatformKeyValue() ?? '',
                'status' => $application->getStatus()->value,
                'private' => $application->isPrivate(),
                'updatedAt' => $application->getUpdatedAt()?->format(DATE_ATOM),
            ]);
            $indexed++;
        }

        if ($input->isInteractive()) {
            $this->getSymfonyStyle($input, $output)->success('Platform applications indexed: ' . $indexed);
        }

        return Command::SUCCESS;
    }
}
