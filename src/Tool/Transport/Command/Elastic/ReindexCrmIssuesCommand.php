<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command\Elastic;

use App\Crm\Application\Projection\CrmIssueProjection;
use App\Crm\Domain\Entity\CrmGithubWebhookEvent;
use App\Crm\Infrastructure\Repository\CrmGithubWebhookEventRepository;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function is_array;
use function is_int;
use function is_string;
use function trim;

#[AsCommand(
    name: self::NAME,
    description: 'Index CRM GitHub issues in Elasticsearch.',
)]
final class ReindexCrmIssuesCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'elastic:reindex:crm-issues';

    public function __construct(
        private readonly CrmGithubWebhookEventRepository $webhookEventRepository,
        private readonly ElasticsearchServiceInterface $elasticsearchService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexed = 0;

        /** @var CrmGithubWebhookEvent $event */
        foreach ($this->webhookEventRepository->findBy(['eventName' => 'issues'], ['createdAt' => 'DESC']) as $event) {
            $payload = $event->getPayload();
            $issue = is_array($payload['issue'] ?? null) ? $payload['issue'] : [];

            $issueNumber = $issue['number'] ?? null;
            $issueTitle = $issue['title'] ?? null;
            $issueState = $issue['state'] ?? null;

            $this->elasticsearchService->index(CrmIssueProjection::INDEX_NAME, $event->getId(), [
                'id' => $event->getId(),
                'deliveryId' => $event->getDeliveryId(),
                'repositoryFullName' => $event->getRepositoryFullName(),
                'eventAction' => $event->getEventAction(),
                'issueNumber' => is_int($issueNumber) ? $issueNumber : null,
                'issueTitle' => is_string($issueTitle) ? trim($issueTitle) : null,
                'issueState' => is_string($issueState) ? trim($issueState) : null,
                'updatedAt' => $event->getUpdatedAt()?->format(DATE_ATOM),
            ]);
            $indexed++;
        }

        if ($input->isInteractive()) {
            $this->getSymfonyStyle($input, $output)->success('CRM issues indexed: ' . $indexed);
        }

        return Command::SUCCESS;
    }
}
