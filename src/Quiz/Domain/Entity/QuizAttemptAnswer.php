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
#[ORM\Table(name: 'quiz_attempt_answer', indexes: [
    new ORM\Index(name: 'idx_quiz_attempt_answer_attempt_id', columns: ['attempt_id']),
    new ORM\Index(name: 'idx_quiz_attempt_answer_question_id', columns: ['question_id']),
])]
class QuizAttemptAnswer implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: QuizAttempt::class)]
    #[ORM\JoinColumn(name: 'attempt_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private QuizAttempt $attempt;

    #[ORM\ManyToOne(targetEntity: QuizQuestion::class)]
    #[ORM\JoinColumn(name: 'question_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private QuizQuestion $question;

    #[ORM\ManyToOne(targetEntity: QuizAnswer::class)]
    #[ORM\JoinColumn(name: 'selected_answer_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?QuizAnswer $selectedAnswer = null;

    #[ORM\Column(name: 'is_correct', type: Types::BOOLEAN)]
    private bool $isCorrect = false;

    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function setAttempt(QuizAttempt $attempt): self
    {
        $this->attempt = $attempt;

        return $this;
    }

    public function setQuestion(QuizQuestion $question): self
    {
        $this->question = $question;

        return $this;
    }

    public function setSelectedAnswer(?QuizAnswer $selectedAnswer): self
    {
        $this->selectedAnswer = $selectedAnswer;

        return $this;
    }

    public function setIsCorrect(bool $isCorrect): self
    {
        $this->isCorrect = $isCorrect;

        return $this;
    }
}
