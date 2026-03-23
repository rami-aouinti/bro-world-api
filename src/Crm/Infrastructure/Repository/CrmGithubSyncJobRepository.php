<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\Repository;

use App\Crm\Domain\Entity\CrmGithubSyncJob;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CrmGithubSyncJob|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method CrmGithubSyncJob[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
final class CrmGithubSyncJobRepository extends BaseRepository
{
    protected static string $entityName = CrmGithubSyncJob::class;

    protected static array $searchColumns = [
        'id',
        'applicationSlug',
        'owner',
        'status',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry,
    ) {
    }
}
