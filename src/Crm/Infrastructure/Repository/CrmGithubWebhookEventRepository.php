<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\Repository;

use App\Crm\Domain\Entity\CrmGithubWebhookEvent;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CrmGithubWebhookEvent|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method CrmGithubWebhookEvent[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
final class CrmGithubWebhookEventRepository extends BaseRepository
{
    protected static string $entityName = CrmGithubWebhookEvent::class;

    protected static array $searchColumns = [
        'id',
        'deliveryId',
        'eventName',
        'repositoryFullName',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry,
    ) {
    }
}
