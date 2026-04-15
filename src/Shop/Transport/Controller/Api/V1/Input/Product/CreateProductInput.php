<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Input\Product;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateProductInput
{
    #[Assert\NotBlank(message: 'name is required.')]
    public string $name = '';

    #[Assert\NotBlank(message: 'sku is required.')]
    #[Assert\Regex(pattern: '/^[A-Z0-9][A-Z0-9_-]*$/', message: 'sku format is invalid.')]
    public string $sku = '';

    #[Assert\GreaterThan(value: 0, message: 'price must be greater than 0.')]
    public float $price = 0;

    #[Assert\GreaterThanOrEqual(value: 0, message: 'stock must be greater than or equal to 0.')]
    public int $stock = 0;

    #[Assert\GreaterThanOrEqual(value: 0, message: 'coinsAmount must be greater than or equal to 0.')]
    public int $coinsAmount = 0;

    public ?string $description = null;
    public ?string $photo = null;
    public ?string $currencyCode = null;
    public ?string $categoryId = null;

    /**
     * @var array<int, string>
     */
    public array $tagIds = [];

    public bool $isFeatured = false;
    public ?string $status = null;
    public ?string $shopId = null;
    public ?string $applicationSlug = null;

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $input = new self();
        $input->name = trim((string)($payload['name'] ?? ''));
        $input->sku = strtoupper(trim((string)($payload['sku'] ?? '')));
        $input->price = (float)($payload['price'] ?? 0);
        $input->stock = (int)($payload['stock'] ?? 0);
        $input->coinsAmount = (int)($payload['coinsAmount'] ?? 0);
        $input->description = ($payload['description'] ?? null) !== null ? (string)$payload['description'] : null;
        $input->photo = is_string($payload['photo'] ?? null) ? trim((string)$payload['photo']) : null;
        $input->currencyCode = is_string($payload['currencyCode'] ?? null) ? trim((string)$payload['currencyCode']) : null;
        $input->categoryId = is_string($payload['categoryId'] ?? null) ? trim((string)$payload['categoryId']) : null;
        $input->tagIds = array_values(array_filter((array)($payload['tagIds'] ?? []), static fn (mixed $id): bool => is_string($id)));
        $input->isFeatured = (bool)($payload['isFeatured'] ?? false);
        $input->status = is_string($payload['status'] ?? null) ? (string)$payload['status'] : null;
        $input->shopId = is_string($payload['shopId'] ?? null) ? trim((string)$payload['shopId']) : null;
        $input->applicationSlug = is_string($payload['applicationSlug'] ?? null) ? trim((string)$payload['applicationSlug']) : null;

        return $input;
    }
}
