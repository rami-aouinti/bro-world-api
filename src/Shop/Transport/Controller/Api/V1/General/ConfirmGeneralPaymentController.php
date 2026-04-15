<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\Shop\Application\Service\PaymentService;
use App\Shop\Transport\Controller\Api\V1\Input\Payment\ConfirmPaymentInput;
use App\Shop\Transport\Controller\Api\V1\Input\Payment\PaymentInputValidator;
use App\Shop\Transport\Controller\Api\V1\Input\Support\ValidationResponseFactory;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Shop')]
final readonly class ConfirmGeneralPaymentController
{
    public function __construct(
        private PaymentService $paymentService,
        private PaymentInputValidator $paymentInputValidator,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/shop/general/orders/{orderId}/payment-confirm', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Confirm a payment for a global checkout order.')]
    public function __invoke(string $orderId, Request $request): JsonResponse
    {
        try {
            $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ValidationResponseFactory::invalidJson();
        }

        $input = ConfirmPaymentInput::fromArray($payload);
        $validationResponse = $this->paymentInputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $transaction = $this->paymentService->confirmPayment(null, $orderId, $input->providerReference, $payload);

        return new JsonResponse([
            'id' => $transaction->getId(),
            'orderId' => $transaction->getOrder()?->getId(),
            'provider' => $transaction->getProvider(),
            'providerReference' => $transaction->getProviderReference(),
            'status' => $transaction->getStatus()->value,
        ]);
    }
}
