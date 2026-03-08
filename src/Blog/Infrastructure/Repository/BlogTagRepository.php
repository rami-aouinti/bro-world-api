<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Repository;

use App\Blog\Domain\Entity\BlogTag;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlogTagRepository extends BaseRepository
{
    protected static string $entityName = BlogTag::class;
    protected static array $searchColumns = ['id', 'label'];

    public function __construct(protected ManagerRegistry $managerRegistry) {}
}
