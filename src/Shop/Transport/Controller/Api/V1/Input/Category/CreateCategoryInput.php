<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Input\Category;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateCategoryInput
{
    #[Assert\NotBlank(message: 'name is required.')]
    public string $name = '';

    public ?string $slug = null;
    public ?string $description = null;

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $input = new self();
        $input->name = trim((string) ($payload['name'] ?? ''));
        $input->slug = is_string($payload['slug'] ?? null) ? trim((string) $payload['slug']) : null;
        $input->description = ($payload['description'] ?? null) !== null ? (string) $payload['description'] : null;

        return $input;
    }
}
