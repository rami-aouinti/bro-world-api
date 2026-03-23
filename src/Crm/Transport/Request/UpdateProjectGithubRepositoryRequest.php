<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\Validator\Constraints as Assert;

use function array_key_exists;
use function is_array;
use function is_string;
use function trim;

final class UpdateProjectGithubRepositoryRequest
{
    #[Assert\Length(max: 255)]
    public ?string $defaultBranch = null;

    #[Assert\Length(max: 40)]
    public ?string $syncStatus = null;

    #[Assert\Type(type: 'array')]
    public ?array $metadata = null;

    public static function fromArray(array $payload): self
    {
        $request = new self();

        if (array_key_exists('defaultBranch', $payload)) {
            $request->defaultBranch = is_string($payload['defaultBranch'])
                ? trim($payload['defaultBranch'])
                : null;
        }

        if (array_key_exists('syncStatus', $payload)) {
            $request->syncStatus = is_string($payload['syncStatus'])
                ? trim($payload['syncStatus'])
                : null;
        }

        if (array_key_exists('metadata', $payload)) {
            $request->metadata = is_array($payload['metadata']) ? $payload['metadata'] : null;
        }

        return $request;
    }
}
