<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Input\Product;

use App\Shop\Transport\Controller\Api\V1\Input\Support\ValidationResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class ProductInputValidator
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    public function validate(CreateProductInput|PatchProductInput $input): ?JsonResponse
    {
        if ($input instanceof CreateProductInput) {
            $violations = $this->validator->validate($input);
            if ($violations->count() > 0) {
                return ValidationResponseFactory::fromViolations($violations);
            }

            return null;
        }

        $errors = [];
        if ($input->name !== null && $input->name === '') {
            $errors[] = ['field' => 'name', 'message' => 'name cannot be blank.', 'code' => 'NAME_REQUIRED'];
        }
        if ($input->sku !== null && ($input->sku === '' || preg_match('/^[A-Z0-9][A-Z0-9_-]*$/', $input->sku) !== 1)) {
            $errors[] = ['field' => 'sku', 'message' => 'sku format is invalid.', 'code' => 'SKU_INVALID'];
        }
        if ($input->price !== null && $input->price <= 0) {
            $errors[] = ['field' => 'price', 'message' => 'price must be greater than 0.', 'code' => 'PRICE_INVALID'];
        }
        if ($input->stock !== null && $input->stock < 0) {
            $errors[] = ['field' => 'stock', 'message' => 'stock must be greater than or equal to 0.', 'code' => 'STOCK_INVALID'];
        }

        if ($errors === []) {
            return null;
        }

        return new JsonResponse([
            'message' => 'Validation failed.',
            'errors' => $errors,
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
