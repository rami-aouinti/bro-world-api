<?php

declare(strict_types=1);

namespace App\Quiz\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Quiz\Domain\Entity\QuizCategory;
use Doctrine\Persistence\ManagerRegistry;

class QuizCategoryRepository extends BaseRepository
{
    protected static string $entityName = QuizCategory::class;
    protected static array $searchColumns = ['id', 'name', 'slug'];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    /**
     * @return list<QuizCategory>
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('category')
            ->andWhere('category.isActive = true')
            ->orderBy('category.position', 'ASC')
            ->addOrderBy('category.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneBySlug(string $slug): ?QuizCategory
    {
        $result = $this->findOneBy([
            'slug' => $slug,
        ]);

        return $result instanceof QuizCategory ? $result : null;
    }
}
