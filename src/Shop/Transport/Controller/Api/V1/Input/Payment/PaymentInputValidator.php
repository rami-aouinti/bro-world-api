<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Input\Payment;

use App\Shop\Transport\Controller\Api\V1\Input\Support\ValidationResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class PaymentInputValidator
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    public function validate(ConfirmPaymentInput $input): ?JsonResponse
    {
        $violations = $this->validator->validate($input);
        if ($violations->count() === 0) {
            return null;
        }

        return ValidationResponseFactory::fromViolations($violations);
    }
}
