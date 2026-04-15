<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Input\Product;

final class PatchProductInput
{
    public ?string $name = null;
    public ?string $sku = null;
    public ?float $price = null;
    public ?int $stock = null;
    public ?int $coinsAmount = null;
    public ?int $promotionPercentage = null;
    public ?string $description = null;
    public ?string $texture = null;
    public ?string $photo = null;
    public ?string $currencyCode = null;
    public ?string $categoryId = null;
    public ?string $seoTitle = null;
    public ?string $seoDescription = null;
    /**
     * @var array<int, string>|null
     */
    public ?array $seoKeywords = null;

    /**
     * @var array<int, string>|null
     */
    public ?array $tagIds = null;
    /**
     * @var array<int, string>|null
     */
    public ?array $similarProductIds = null;

    public ?bool $isFeatured = null;
    public ?string $status = null;

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $input = new self();
        $input->name = array_key_exists('name', $payload) ? trim((string)$payload['name']) : null;
        $input->sku = array_key_exists('sku', $payload) ? strtoupper(trim((string)$payload['sku'])) : null;
        $input->price = array_key_exists('price', $payload) ? (float)$payload['price'] : null;
        $input->stock = array_key_exists('stock', $payload) ? (int)$payload['stock'] : null;
        $input->coinsAmount = array_key_exists('coinsAmount', $payload) ? (int)$payload['coinsAmount'] : null;
        $input->promotionPercentage = array_key_exists('promotionPercentage', $payload) ? (int)$payload['promotionPercentage'] : null;
        $input->description = array_key_exists('description', $payload) ? (($payload['description'] ?? null) !== null ? (string)$payload['description'] : null) : null;
        $input->texture = array_key_exists('texture', $payload) ? (($payload['texture'] ?? null) !== null ? (string)$payload['texture'] : null) : null;
        $input->photo = array_key_exists('photo', $payload) ? (($payload['photo'] ?? null) !== null ? trim((string)$payload['photo']) : null) : null;
        $input->currencyCode = array_key_exists('currencyCode', $payload) ? (($payload['currencyCode'] ?? null) !== null ? (string)$payload['currencyCode'] : null) : null;
        $input->categoryId = array_key_exists('categoryId', $payload) ? (($payload['categoryId'] ?? null) !== null ? (string)$payload['categoryId'] : null) : null;
        $input->seoTitle = array_key_exists('seoTitle', $payload) ? (($payload['seoTitle'] ?? null) !== null ? (string)$payload['seoTitle'] : null) : null;
        $input->seoDescription = array_key_exists('seoDescription', $payload) ? (($payload['seoDescription'] ?? null) !== null ? (string)$payload['seoDescription'] : null) : null;
        if (array_key_exists('seoKeywords', $payload)) {
            $input->seoKeywords = array_values(array_filter((array)$payload['seoKeywords'], static fn (mixed $keyword): bool => is_string($keyword) && trim($keyword) !== ''));
        }
        if (array_key_exists('tagIds', $payload)) {
            $input->tagIds = array_values(array_filter((array)$payload['tagIds'], static fn (mixed $id): bool => is_string($id)));
        }
        if (array_key_exists('similarProductIds', $payload)) {
            $input->similarProductIds = array_values(array_filter((array)$payload['similarProductIds'], static fn (mixed $id): bool => is_string($id)));
        }
        $input->isFeatured = array_key_exists('isFeatured', $payload) ? (bool)$payload['isFeatured'] : null;
        $input->status = array_key_exists('status', $payload) ? (($payload['status'] ?? null) !== null ? (string)$payload['status'] : null) : null;

        return $input;
    }
}
