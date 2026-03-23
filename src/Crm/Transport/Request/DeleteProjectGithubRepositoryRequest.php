<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use Symfony\Component\Validator\Constraints as Assert;

use function array_key_exists;
use function in_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function strtolower;
use function trim;

final class DeleteProjectGithubRepositoryRequest
{
    #[Assert\Type(type: 'bool')]
    public bool $deleteRemote = false;

    public static function fromArray(array $payload): self
    {
        $request = new self();
        if (array_key_exists('deleteRemote', $payload)) {
            $request->deleteRemote = self::toBool($payload['deleteRemote']);
        }

        return $request;
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
