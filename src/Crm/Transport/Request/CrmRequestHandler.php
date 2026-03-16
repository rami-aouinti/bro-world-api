<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class CrmRequestHandler
{
    public function __construct(
        private ValidatorInterface $validator,
        private CrmApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    public function decodeJson(Request $request): array|JsonResponse
    {
        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        return $payload;
    }

    /**
     * @param class-string $dtoClass
     */
    public function mapAndValidate(array $payload, string $dtoClass): mixed
    {
        if (!method_exists($dtoClass, 'fromArray')) {
            throw new \InvalidArgumentException(sprintf('DTO class "%s" must implement static fromArray(array $payload): self.', $dtoClass));
        }

        $input = $dtoClass::fromArray($payload);
        $violations = $this->validator->validate($input);
        if ($violations->count() > 0) {
            return $this->errorResponseFactory->validationFailed($violations);
        }

        return $input;
    }
}
