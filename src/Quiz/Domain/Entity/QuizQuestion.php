<?php

declare(strict_types=1);

namespace App\Quiz\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'quiz_question')]
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

    #[ORM\Column(name: 'title', type: 'text')]
    private string $title = '';

    #[ORM\Column(name: 'level', type: 'string', length: 50)]
    private string $level = 'easy';

    #[ORM\Column(name: 'category', type: 'string', length: 100)]
    private string $category = '';

    /** @var Collection<int, QuizAnswer> */
    #[ORM\OneToMany(targetEntity: QuizAnswer::class, mappedBy: 'question', cascade: ['remove'])]
    private Collection $answers;

    public function __construct() { $this->id = $this->createUuid(); $this->answers = new ArrayCollection(); }
    #[Override] public function getId(): string { return $this->id->toString(); }
    public function getQuiz(): Quiz { return $this->quiz; }
    public function setQuiz(Quiz $quiz): self { $this->quiz = $quiz; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getLevel(): string { return $this->level; }
    public function setLevel(string $level): self { $this->level = $level; return $this; }
    public function getCategory(): string { return $this->category; }
    public function setCategory(string $category): self { $this->category = $category; return $this; }
    /** @return Collection<int, QuizAnswer> */ public function getAnswers(): Collection { return $this->answers; }
}
