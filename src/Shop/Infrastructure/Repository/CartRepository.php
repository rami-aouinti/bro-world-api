<?php

declare(strict_types=1);

namespace App\Shop\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Shop\Domain\Entity\Cart as Entity;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class CartRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry,
    ) {
    }

    public function findActiveByUserAndShop(string $userId, string $shopId): ?Entity
    {
        /** @var Entity|null $cart */
        $cart = $this->findOneBy([
            'user' => $userId,
            'shop' => $shopId,
            'isActive' => true,
        ]);

        return $cart;
    }
}
