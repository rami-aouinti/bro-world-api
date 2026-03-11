<?php

declare(strict_types=1);

namespace App\Quiz\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'quiz_answer', indexes: [
    new ORM\Index(name: 'idx_quiz_answer_question_id', columns: ['question_id']),
    new ORM\Index(name: 'idx_quiz_answer_position', columns: ['position']),
])]
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

    #[ORM\Column(name: 'label', type: Types::TEXT)]
    private string $label = '';

    #[ORM\Column(name: 'correct', type: Types::BOOLEAN)]
    private bool $correct = false;

    #[ORM\Column(name: 'position', type: Types::INTEGER, options: ['default' => 1])]
    private int $position = 1;

    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getQuestion(): QuizQuestion
    {
        return $this->question;
    }

    public function setQuestion(QuizQuestion $question): self
    {
        $this->question = $question;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function isCorrect(): bool
    {
        return $this->correct;
    }

    public function setCorrect(bool $correct): self
    {
        $this->correct = $correct;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = max(1, $position);

        return $this;
    }
}
