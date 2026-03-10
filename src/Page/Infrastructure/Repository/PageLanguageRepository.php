<?php

declare(strict_types=1);

namespace App\Page\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Page\Domain\Entity\PageLanguage as Entity;
use App\Page\Domain\Repository\Interfaces\PageLanguageRepositoryInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class PageLanguageRepository extends BaseRepository implements PageLanguageRepositoryInterface
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
        'code',
        'label',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }
}
