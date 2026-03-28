<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Repository;

use App\Library\Domain\Entity\LibraryFile;
use App\User\Domain\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

/**
 * @extends ServiceEntityRepository<LibraryFile>
 */
class LibraryFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LibraryFile::class);
    }

    public function findOneByIdAndOwner(string $id, User $owner): ?LibraryFile
    {
        return $this->findOneBy([
            'id' => $id,
            'owner' => $owner,
        ]);
    }

    /**
     * @return list<LibraryFile>
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('file')
            ->andWhere('file.owner = :owner')
            ->setParameter('owner', $owner->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('file.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
