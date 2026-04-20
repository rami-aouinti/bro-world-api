<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateTeacherInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $userId = '';
}
