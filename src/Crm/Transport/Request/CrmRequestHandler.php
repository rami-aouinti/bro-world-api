<?php

declare(strict_types=1);

namespace App\Crm\Transport\Request;

use JsonException;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class CrmRequestHandler
{
    public function __construct(
        private ValidatorInterface $validator,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmDateParser $crmDateParser,
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
     * @param list<string>|string|null $validationGroups
     */
    public function mapAndValidate(
        array $payload,
        string $dtoClass,
        array|string|null $validationGroups = null,
        string $mapperMethod = 'fromArray',
    ): mixed
    {
        if (!method_exists($dtoClass, $mapperMethod)) {
            throw new \InvalidArgumentException(sprintf(
                'DTO class "%s" must implement static %s(array $payload): self.',
                $dtoClass,
                $mapperMethod,
            ));
        }

        $input = $dtoClass::{$mapperMethod}($payload);
        $violations = $this->validator->validate($input, groups: $validationGroups);
        if ($violations->count() > 0) {
            return $this->errorResponseFactory->validationFailed($violations);
        }

        return $input;
    }

    public function parseNullableIso8601(?string $value, string $field): DateTimeImmutable|JsonResponse|null
    {
        return $this->crmDateParser->parseNullableIso8601($value, $field);
    }
}
