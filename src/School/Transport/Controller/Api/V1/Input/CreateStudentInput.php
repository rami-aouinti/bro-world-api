<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateStudentInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $userId = '';

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $classId = '';
}
