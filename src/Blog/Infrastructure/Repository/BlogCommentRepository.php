<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Repository;

use App\Blog\Domain\Entity\BlogComment;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlogCommentRepository extends BaseRepository
{
    protected static string $entityName = BlogComment::class;
    protected static array $searchColumns = ['id', 'content'];

    public function __construct(protected ManagerRegistry $managerRegistry) {}
}
