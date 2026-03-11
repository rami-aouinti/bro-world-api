<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Checkout;

use App\Shop\Application\Message\CheckoutCommand;
use App\Shop\Domain\Entity\Order;
use App\User\Domain\Entity\User;
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
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/shop/{applicationSlug}/checkout/{shopId}', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, string $shopId, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Authenticated user required.');
        }

        $payload = (array)json_decode((string)$request->getContent(), true);

        $billingAddress = trim((string)($payload['billingAddress'] ?? ''));
        $shippingAddress = trim((string)($payload['shippingAddress'] ?? ''));
        $email = trim((string)($payload['email'] ?? ''));
        $phone = trim((string)($payload['phone'] ?? ''));
        $shippingMethod = trim((string)($payload['shippingMethod'] ?? ''));

        if ($billingAddress === '' || $shippingAddress === '' || $email === '' || $phone === '' || $shippingMethod === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Missing required checkout payload.');
        }

        $envelope = $this->messageBus->dispatch(new CheckoutCommand(
            operationId: $request->headers->get('x-request-id', uniqid('checkout-', true)),
            shopId: $shopId,
            userId: $user->getId(),
            billingAddress: $billingAddress,
            shippingAddress: $shippingAddress,
            email: $email,
            phone: $phone,
            shippingMethod: $shippingMethod,
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
            'subtotal' => $order->getSubtotal(),
            'itemsCount' => $order->getItems()->count(),
            'createdAt' => $order->getCreatedAt()?->format(DATE_ATOM),
        ], JsonResponse::HTTP_CREATED);
    }
}
