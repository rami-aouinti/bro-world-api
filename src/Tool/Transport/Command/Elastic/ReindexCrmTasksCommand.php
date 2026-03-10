<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command\Elastic;

use App\Crm\Application\Projection\CrmTaskProjection;
use App\Crm\Domain\Entity\Task;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_map;

#[AsCommand(
    name: self::NAME,
    description: 'Index CRM tasks in Elasticsearch.',
)]
final class ReindexCrmTasksCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'elastic:reindex:crms';

    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly ElasticsearchServiceInterface $elasticsearchService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexed = 0;

        /** @var Task $task */
        foreach ($this->taskRepository->findBy([], [
            'createdAt' => 'DESC',
        ]) as $task) {
            $this->elasticsearchService->index(CrmTaskProjection::INDEX_NAME, $task->getId(), [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'projectName' => $task->getProject()?->getName() ?? '',
                'sprintName' => $task->getSprint()?->getName() ?? '',
                'taskRequests' => array_map(static fn ($request): string => $request->getTitle(), $task->getTaskRequests()->toArray()),
                'updatedAt' => $task->getUpdatedAt()?->format(DATE_ATOM),
            ]);
            $indexed++;
        }

        if ($input->isInteractive()) {
            $this->getSymfonyStyle($input, $output)->success('CRM tasks indexed: ' . $indexed);
        }

        return Command::SUCCESS;
    }
}
