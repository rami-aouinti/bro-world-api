<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Input;

use App\School\Domain\Enum\ExamStatus;
use App\School\Domain\Enum\ExamType;
use App\School\Domain\Enum\Term;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateExamInput
{
    #[Assert\NotBlank]
    public string $title = '';

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $classId = '';


    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $courseId = '';

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $teacherId = '';

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [ExamType::class, 'values'])]
    public string $type = '';

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [ExamStatus::class, 'values'])]
    public string $status = '';

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [Term::class, 'values'])]
    public string $term = '';
}
