<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Checkout;

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
final readonly class CheckoutController
{
    public function __construct(
        private Security $security,
        private CheckoutInputValidator $checkoutInputValidator,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/shop/checkout/{shopId}', methods: [Request::METHOD_POST])]
        public function __invoke(string $applicationSlug, string $shopId, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Authenticated user required.');
        }

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
            operationId: $request->headers->get('x-request-id', uniqid('checkout-', true)),
            applicationSlug: $applicationSlug,
            shopId: $shopId,
            userId: $user->getId(),
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
