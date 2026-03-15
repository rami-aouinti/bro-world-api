<?php

declare(strict_types=1);

namespace App\Recruit\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Recruit\Domain\Entity\Interview;
use App\Recruit\Domain\Entity\InterviewFeedback as Entity;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class InterviewFeedbackRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry,
    ) {
    }

    public function findOneByInterviewAndInterviewer(Interview $interview, User $interviewer): ?Entity
    {
        $feedback = $this->findOneBy([
            'interview' => $interview,
            'interviewer' => $interviewer,
        ]);

        return $feedback instanceof Entity ? $feedback : null;
    }

    /**
     * @return array<int, Entity>
     */
    public function findByInterview(Interview $interview): array
    {
        return $this->findBy([
            'interview' => $interview,
        ], [
            'createdAt' => 'ASC',
        ]);
    }
}
