<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\Shop\Application\Service\PaymentService;
use App\Shop\Transport\Controller\Api\V1\Input\Support\ValidationResponseFactory;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Shop')]
final readonly class GeneralPaymentWebhookController
{
    public function __construct(
        private PaymentService $paymentService,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws JsonException
     */
    #[Route('/v1/shop/general/payments/webhook', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Handle global payment provider webhook (no applicationSlug required).', security: [])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ValidationResponseFactory::invalidJson();
        }

        $signature = $request->headers->get('x-signature');
        $provider = $request->query->get('provider');
        $transaction = $this->paymentService->processWebhook($payload, $signature, is_string($provider) ? $provider : null);

        if ($transaction === null) {
            return new JsonResponse([
                'processed' => false,
            ], JsonResponse::HTTP_ACCEPTED);
        }

        return new JsonResponse([
            'processed' => true,
            'transactionId' => $transaction->getId(),
            'providerReference' => $transaction->getProviderReference(),
            'status' => $transaction->getStatus()->value,
        ]);
    }
}
