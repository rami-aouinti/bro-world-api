<?php

declare(strict_types=1);

namespace App\Quiz\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'quiz_answer')]
class QuizAnswer implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: QuizQuestion::class)]
    #[ORM\JoinColumn(name: 'question_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private QuizQuestion $question;

    #[ORM\Column(name: 'label', type: 'text')]
    private string $label = '';

    #[ORM\Column(name: 'correct', type: 'boolean')]
    private bool $correct = false;

    public function __construct() { $this->id = $this->createUuid(); }
    #[Override] public function getId(): string { return $this->id->toString(); }
    public function getQuestion(): QuizQuestion { return $this->question; }
    public function setQuestion(QuizQuestion $question): self { $this->question = $question; return $this; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }
    public function isCorrect(): bool { return $this->correct; }
    public function setCorrect(bool $correct): self { $this->correct = $correct; return $this; }
}
