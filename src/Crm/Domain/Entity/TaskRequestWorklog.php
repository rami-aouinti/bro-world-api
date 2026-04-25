<?php

declare(strict_types=1);

namespace App\Crm\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;

#[ORM\Entity]
#[ORM\Table(name: 'crm_task_request_worklog')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class TaskRequestWorklog implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: TaskRequest::class, inversedBy: 'worklogs')]
    #[ORM\JoinColumn(name: 'task_request_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?TaskRequest $taskRequest = null;

    #[ORM\ManyToOne(targetEntity: Employee::class)]
    #[ORM\JoinColumn(name: 'employee_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Employee $employee = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'logged_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $loggedByUser = null;

    #[ORM\Column(name: 'hours', type: Types::FLOAT)]
    #[Assert\Positive]
    private float $hours = 0.0;

    #[ORM\Column(name: 'logged_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $loggedAt;

    #[ORM\Column(name: 'comment', type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->loggedAt = new DateTimeImmutable();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getTaskRequest(): ?TaskRequest
    {
        return $this->taskRequest;
    }

    public function setTaskRequest(?TaskRequest $taskRequest): self
    {
        $this->taskRequest = $taskRequest;

        return $this;
    }

    public function getEmployee(): ?Employee
    {
        return $this->employee;
    }

    public function setEmployee(?Employee $employee): self
    {
        $this->employee = $employee;

        return $this;
    }

    public function getLoggedByUser(): ?User
    {
        return $this->loggedByUser;
    }

    public function setLoggedByUser(?User $loggedByUser): self
    {
        $this->loggedByUser = $loggedByUser;

        return $this;
    }

    public function getHours(): float
    {
        return $this->hours;
    }

    public function setHours(float $hours): self
    {
        $this->hours = $hours;

        return $this;
    }

    public function getLoggedAt(): DateTimeImmutable
    {
        return $this->loggedAt;
    }

    public function setLoggedAt(DateTimeImmutable $loggedAt): self
    {
        $this->loggedAt = $loggedAt;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }
}
