<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateGithubProjectBoardRequest
{
    #[Assert\NotBlank]
    public ?string $owner = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $title = null;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        $request->owner = isset($payload['owner']) ? trim((string)$payload['owner']) : null;
        $request->title = isset($payload['title']) ? trim((string)$payload['title']) : null;

        return $request;
    }
}
