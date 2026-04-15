<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Payment;

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
final readonly class ConfirmPaymentController
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
    #[Route('/v1/shop/applications/{applicationSlug}/orders/{orderId}/payment-confirm', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(
        summary: 'Confirm a payment for an order (private endpoint, full authentication required).',
        security: [[
            'Bearer' => [],
        ]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['providerReference'],
                properties: [
                    new OA\Property(property: 'providerReference', type: 'string', example: 'pi_3Nxx...'),
                    new OA\Property(property: 'provider', type: 'string', enum: ['paypal', 'stripe', 'mock'], nullable: true),
                    new OA\Property(property: 'paymentMethod', type: 'string', enum: ['paypal', 'stripe', 'mock'], nullable: true),
                ],
            ),
        ),
    )]
    #[OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Forbidden. The order does not belong to the authenticated user or requested application.')]
    public function __invoke(string $applicationSlug, string $orderId, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);

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

        $transaction = $this->paymentService->confirmPayment($applicationSlug, $orderId, $input->providerReference, $payload);

        return new JsonResponse([
            'id' => $transaction->getId(),
            'orderId' => $transaction->getOrder()?->getId(),
            'provider' => $transaction->getProvider(),
            'providerReference' => $transaction->getProviderReference(),
            'status' => $transaction->getStatus()->value,
        ]);
    }
}
