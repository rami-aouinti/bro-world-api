<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Notification\Domain\Entity\NotificationTemplate;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method NotificationTemplate|null find(string $id, $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 */
class NotificationTemplateRepository extends BaseRepository
{
    protected static string $entityName = NotificationTemplate::class;
    protected static array $searchColumns = ['id', 'name', 'providerTemplateId'];

    public function __construct(
        protected ManagerRegistry $managerRegistry,
    ) {
    }

    /**
     * @return NotificationTemplate[]
     */
    public function findList(int $limit = 50, int $offset = 0): array
    {
        $result = $this->createQueryBuilder('nt')
            ->orderBy('nt.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_values(array_filter($result, static fn ($template): bool => $template instanceof NotificationTemplate));
    }

    public function findOneByProviderTemplateId(int $providerTemplateId): ?NotificationTemplate
    {
        $template = $this->createQueryBuilder('nt')
            ->andWhere('nt.providerTemplateId = :providerTemplateId')
            ->setParameter('providerTemplateId', $providerTemplateId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $template instanceof NotificationTemplate ? $template : null;
    }
}
