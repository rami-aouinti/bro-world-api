<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateCourseInput
{
    #[Assert\NotBlank]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $classId = '';

    #[Assert\Uuid]
    public ?string $teacherId = null;

    public ?string $contentHtml = null;
}
