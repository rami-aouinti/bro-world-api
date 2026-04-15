<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\Shop\Application\Service\MoneyFormatter;
use App\Shop\Application\Service\PaymentService;
use App\Shop\Transport\Controller\Api\V1\Input\Payment\CreatePaymentIntentInput;
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

use function trim;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Shop')]
final readonly class CreateGeneralPaymentIntentController
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
    #[Route('/v1/shop/general/orders/{orderId}/payment-intent', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Create a payment intent for a global checkout order.')]
    public function __invoke(string $orderId, Request $request): JsonResponse
    {
        $rawContent = (string)$request->getContent();
        if (trim($rawContent) === '') {
            $payload = [];
        } else {
            try {
                $payload = (array)json_decode($rawContent, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return ValidationResponseFactory::invalidJson();
            }
        }

        $input = CreatePaymentIntentInput::fromArray($payload);
        $validationResponse = $this->paymentInputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $transaction = $this->paymentService->createPaymentIntent(null, $orderId, $input->provider, $input->paymentMethod);

        return new JsonResponse([
            'id' => $transaction->getId(),
            'orderId' => $transaction->getOrder()?->getId(),
            'provider' => $transaction->getProvider(),
            'providerReference' => $transaction->getProviderReference(),
            'status' => $transaction->getStatus()->value,
            'amount' => MoneyFormatter::toApiAmount($transaction->getAmount()),
            'currency' => $transaction->getCurrency(),
        ], JsonResponse::HTTP_CREATED);
    }
}
