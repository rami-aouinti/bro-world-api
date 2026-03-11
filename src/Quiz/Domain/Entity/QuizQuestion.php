<?php

declare(strict_types=1);

namespace App\Quiz\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Quiz\Domain\Enum\QuizCategory;
use App\Quiz\Domain\Enum\QuizLevel;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'quiz_question', indexes: [
    new ORM\Index(name: 'idx_quiz_question_level', columns: ['level']),
    new ORM\Index(name: 'idx_quiz_question_category', columns: ['category']),
    new ORM\Index(name: 'idx_quiz_question_position', columns: ['position']),
])]
class QuizQuestion implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Quiz::class)]
    #[ORM\JoinColumn(name: 'quiz_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Quiz $quiz;

    #[ORM\Column(name: 'title', type: Types::TEXT)]
    private string $title = '';

    #[ORM\Column(name: 'level', type: Types::STRING, length: 50, enumType: QuizLevel::class, options: ['default' => QuizLevel::EASY->value])]
    private QuizLevel $level = QuizLevel::EASY;

    #[ORM\Column(name: 'category', type: Types::STRING, length: 100, enumType: QuizCategory::class, options: ['default' => QuizCategory::GENERAL->value])]
    private QuizCategory $category = QuizCategory::GENERAL;

    #[ORM\Column(name: 'position', type: Types::INTEGER, options: ['default' => 1])]
    private int $position = 1;

    #[ORM\Column(name: 'points', type: Types::INTEGER, options: ['default' => 1])]
    private int $points = 1;

    #[ORM\Column(name: 'explanation', type: Types::TEXT, nullable: true)]
    private ?string $explanation = null;

    /**
     * @var Collection<int, QuizAnswer>
     */
    #[ORM\OneToMany(targetEntity: QuizAnswer::class, mappedBy: 'question', cascade: ['remove'])]
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getLevel(): QuizLevel
    {
        return $this->level;
    }

    public function setLevel(QuizLevel $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function getCategory(): QuizCategory
    {
        return $this->category;
    }

    public function setCategory(QuizCategory $category): self
    {
        $this->category = $category;

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

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): self
    {
        $this->points = max(1, $points);

        return $this;
    }

    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    public function setExplanation(?string $explanation): self
    {
        $this->explanation = $explanation;

        return $this;
    }

    /**
     * @return Collection<int, QuizAnswer>
     */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }
}
