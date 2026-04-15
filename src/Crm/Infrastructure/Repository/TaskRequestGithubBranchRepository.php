<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\Repository;

use App\Crm\Domain\Entity\TaskRequestGithubBranch as Entity;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

use function trim;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class TaskRequestGithubBranchRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
        'repositoryFullName',
        'branchName',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    /**
     * @return list<Entity>
     */
    public function findByRepositoryAndBranch(string $repositoryFullName, string $branchName): array
    {
        /** @var list<Entity> $entities */
        $entities = $this->createQueryBuilder('githubBranch')
            ->addSelect('taskRequest', 'task', 'project', 'company', 'crm', 'application')
            ->leftJoin('githubBranch.taskRequest', 'taskRequest')
            ->leftJoin('taskRequest.task', 'task')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->leftJoin('company.crm', 'crm')
            ->leftJoin('crm.application', 'application')
            ->andWhere('githubBranch.repositoryFullName = :repositoryFullName')
            ->andWhere('githubBranch.branchName = :branchName')
            ->setParameter('repositoryFullName', trim($repositoryFullName))
            ->setParameter('branchName', trim($branchName))
            ->getQuery()
            ->getResult();

        return $entities;
    }

    /**
     * @return list<Entity>
     */
    public function findAllWithProjectContext(): array
    {
        /** @var list<Entity> $entities */
        $entities = $this->createQueryBuilder('githubBranch')
            ->addSelect('taskRequest', 'task', 'project', 'company', 'crm', 'application')
            ->leftJoin('githubBranch.taskRequest', 'taskRequest')
            ->leftJoin('taskRequest.task', 'task')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->leftJoin('company.crm', 'crm')
            ->leftJoin('crm.application', 'application')
            ->getQuery()
            ->getResult();

        return $entities;
    }
}
