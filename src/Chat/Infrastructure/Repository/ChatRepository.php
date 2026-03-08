<?php

declare(strict_types=1);

namespace App\Chat\Infrastructure\Repository;

use App\Chat\Domain\Entity\Chat as Entity;
use App\Chat\Domain\Repository\Interfaces\ChatRepositoryInterface;
use App\General\Infrastructure\Repository\BaseRepository;
use App\Platform\Domain\Entity\Application;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class ChatRepository extends BaseRepository implements ChatRepositoryInterface
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
        'applicationSlug',
    ];

    public function __construct(protected ManagerRegistry $managerRegistry)
    {
    }

    public function findOneByApplication(Application $application): ?Entity
    {
        $chat = $this->findOneBy([
            'application' => $application,
        ]);

        return $chat instanceof Entity ? $chat : null;
    }
}
