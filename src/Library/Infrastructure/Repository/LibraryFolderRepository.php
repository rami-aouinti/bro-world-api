<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Repository;

use App\Library\Domain\Entity\LibraryFolder;
use App\User\Domain\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LibraryFolder>
 */
class LibraryFolderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LibraryFolder::class);
    }

    public function findOneByIdAndOwner(string $id, User $owner): ?LibraryFolder
    {
        return $this->findOneBy([
            'id' => $id,
            'owner' => $owner,
        ]);
    }

    /**
     * @return list<LibraryFolder>
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('folder')
            ->andWhere('folder.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('folder.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
