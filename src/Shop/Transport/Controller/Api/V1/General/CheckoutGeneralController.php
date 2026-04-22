<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\Shop\Application\Message\CheckoutCommand;
use App\Shop\Application\Service\MoneyFormatter;
use App\Shop\Domain\Entity\Order;
use App\Shop\Transport\Controller\Api\V1\Input\Checkout\CheckoutInput;
use App\Shop\Transport\Controller\Api\V1\Input\Checkout\CheckoutInputValidator;
use App\Shop\Transport\Controller\Api\V1\Input\Support\ValidationResponseFactory;
use App\User\Domain\Entity\User;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Shop')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class CheckoutGeneralController
{
    public function __construct(
        private Security $security,
        private CheckoutInputValidator $checkoutInputValidator,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[OA\Parameter(name: 'shopId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: 'f95da407-b9f0-4d5f-a14e-15c4b22af6e3'))]
    #[OA\Post(
        description: 'Manual /api/doc chain step 3/6: POST /v1/shop/general/checkout/{shopId}. Reuse shopId=f95da407-b9f0-4d5f-a14e-15c4b22af6e3 from steps 1-2 and store returned orderId=ord_8cb7be4f-2d27-430d-bc16-5b9fc4f2ef1e for steps 4-5.',
        summary: 'Create an order from the authenticated user cart in the global shop scope.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                examples: [
                    new OA\Examples(
                        example: 'manual_step_3_checkout_input',
                        summary: 'Step 3 input - checkout using shopId from steps 1-2',
                        value: [
                            'billingAddress' => '42 Rue des Fleurs, 75001 Paris, FR',
                            'shippingAddress' => '15 Avenue Victor Hugo, 75016 Paris, FR',
                            'email' => 'alice.martin@example.com',
                            'phone' => '+33123456789',
                            'shippingMethod' => 'express',
                        ],
                    ),
                ],
                required: ['billingAddress', 'shippingAddress', 'email', 'shippingMethod'],
                properties: [
                    new OA\Property(property: 'billingAddress', type: 'string', example: '42 Rue des Fleurs, 75001 Paris, FR'),
                    new OA\Property(property: 'shippingAddress', type: 'string', example: '15 Avenue Victor Hugo, 75016 Paris, FR'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'alice.martin@example.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '+33123456789', nullable: true),
                    new OA\Property(property: 'shippingMethod', type: 'string', example: 'standard'),
                ],
            ),
        ),
    )]
    #[OA\Response(
        response: JsonResponse::HTTP_CREATED,
        description: 'Order created.',
        content: new OA\JsonContent(
            examples: [
                new OA\Examples(
                    example: 'manual_step_3_checkout_output',
                    summary: 'Step 3 output - capture orderId for steps 4-5',
                    value: [
                        'id' => 'ord_8cb7be4f-2d27-430d-bc16-5b9fc4f2ef1e',
                        'status' => 'pending_payment',
                        'subtotal' => 129.9,
                        'itemsCount' => 3,
                        'createdAt' => '2026-04-15T10:12:55+00:00',
                    ],
                ),
            ],
        ),
    )]
    #[OA\Response(
        response: JsonResponse::HTTP_ACCEPTED,
        description: 'Checkout command accepted and processing asynchronously.',
        content: new OA\JsonContent(example: ['message' => 'Checkout command accepted.']),
    )]
    #[OA\Response(
        response: JsonResponse::HTTP_BAD_REQUEST,
        description: 'Invalid JSON payload or invalid request fields.',
        content: new OA\JsonContent(
            examples: [
                new OA\Examples(
                    example: 'invalid_json',
                    summary: 'Malformed JSON body',
                    value: [
                        'message' => 'Validation failed.',
                        'errors' => [[
                            'field' => 'payload',
                            'message' => 'Invalid JSON payload.',
                            'code' => 'INVALID_JSON',
                        ]],
                    ],
                ),
                new OA\Examples(
                    example: 'invalid_fields',
                    summary: 'Missing required field',
                    value: [
                        'message' => 'Validation failed.',
                        'errors' => [[
                            'field' => 'email',
                            'message' => 'This value is not a valid email address.',
                            'code' => 'INVALID_EMAIL',
                        ]],
                    ],
                ),
            ],
        ),
    )]
    #[OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Missing or invalid Bearer token.')]
    #[OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Authenticated user is not allowed to checkout this shop.')]
    #[OA\Response(
        response: JsonResponse::HTTP_NOT_FOUND,
        description: 'Shop not found.',
        content: new OA\JsonContent(example: ['message' => 'Shop not found.']),
    )]
    public function __invoke(string $shopId, Request $request, User $loggedInUser): JsonResponse
    {
        try {
            $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ValidationResponseFactory::invalidJson();
        }

        $input = CheckoutInput::fromArray($payload);
        $validationResponse = $this->checkoutInputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $envelope = $this->messageBus->dispatch(new CheckoutCommand(
            operationId: $request->headers->get('x-request-id', uniqid('checkout-global-', true)),
            applicationSlug: null,
            shopId: $shopId,
            userId: $loggedInUser->getId(),
            billingAddress: $input->billingAddress,
            shippingAddress: $input->shippingAddress,
            email: $input->email,
            phone: $input->phone,
            shippingMethod: $input->shippingMethod,
        ));

        /** @var HandledStamp|null $handled */
        $handled = $envelope->last(HandledStamp::class);
        $order = $handled?->getResult();
        if (!$order instanceof Order) {
            return new JsonResponse([
                'message' => 'Checkout command accepted.',
            ], JsonResponse::HTTP_ACCEPTED);
        }

        return new JsonResponse([
            'id' => $order->getId(),
            'status' => $order->getStatus()->value,
            'subtotal' => MoneyFormatter::toApiAmount($order->getSubtotal()),
            'itemsCount' => $order->getItems()->count(),
            'createdAt' => $order->getCreatedAt()?->format(DATE_ATOM),
        ], JsonResponse::HTTP_CREATED);
    }
}
