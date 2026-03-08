<?php

declare(strict_types=1);

namespace App\Crm\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'crm_task_request')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class TaskRequest implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Task::class, inversedBy: 'taskRequests')]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Task $task = null;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255)]
    private string $title = '';

    #[ORM\Column(name: 'status', type: Types::STRING, length: 50, options: ['default' => 'pending'])]
    private string $status = 'pending';

    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string { return $this->id->toString(); }
    public function getTask(): ?Task { return $this->task; }
    public function setTask(?Task $task): self { $this->task = $task; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
}
