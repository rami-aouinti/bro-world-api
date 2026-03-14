<?php

declare(strict_types=1);

namespace App\Quiz\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\User\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'quiz_attempt', indexes: [
    new ORM\Index(name: 'idx_quiz_attempt_quiz_id', columns: ['quiz_id']),
    new ORM\Index(name: 'idx_quiz_attempt_user_id', columns: ['user_id']),
])]
class QuizAttempt implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Quiz::class)]
    #[ORM\JoinColumn(name: 'quiz_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Quiz $quiz;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'score', type: Types::FLOAT)]
    private float $score = 0.0;

    #[ORM\Column(name: 'passed', type: Types::BOOLEAN)]
    private bool $passed = false;

    #[ORM\Column(name: 'total_questions', type: Types::INTEGER)]
    private int $totalQuestions = 0;

    #[ORM\Column(name: 'correct_answers', type: Types::INTEGER)]
    private int $correctAnswers = 0;

    /** @var Collection<int, QuizAttemptAnswer> */
    #[ORM\OneToMany(targetEntity: QuizAttemptAnswer::class, mappedBy: 'attempt', cascade: ['remove'])]
    private Collection $answers;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->answers = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getQuiz(): Quiz
    {
        return $this->quiz;
    }

    public function setQuiz(Quiz $quiz): self
    {
        $this->quiz = $quiz;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function isPassed(): bool
    {
        return $this->passed;
    }

    public function setPassed(bool $passed): self
    {
        $this->passed = $passed;

        return $this;
    }

    public function getTotalQuestions(): int
    {
        return $this->totalQuestions;
    }

    public function setTotalQuestions(int $totalQuestions): self
    {
        $this->totalQuestions = $totalQuestions;

        return $this;
    }

    public function getCorrectAnswers(): int
    {
        return $this->correctAnswers;
    }

    public function setCorrectAnswers(int $correctAnswers): self
    {
        $this->correctAnswers = $correctAnswers;

        return $this;
    }
}
