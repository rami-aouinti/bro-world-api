<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Application\Service\CrmGithubWebhookService;
use App\Shop\Transport\Controller\Api\V1\Input\Support\ValidationResponseFactory;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'CRM')]
final readonly class GithubWebhookController
{
    public function __construct(
        private CrmGithubWebhookService $crmGithubWebhookService,
    ) {
    }

    /**
     * @throws JsonException
     */
    #[Route('/v1/crm/github/webhook', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Public GitHub webhook endpoint with HMAC signature validation + idempotence guard.', security: [])]
    #[OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Invalid payload or missing required headers.')]
    #[OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Invalid HMAC signature.')]
    public function __invoke(Request $request): JsonResponse
    {
        $rawPayload = (string)$request->getContent();

        try {
            $payload = (array)json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ValidationResponseFactory::invalidJson();
        }

        try {
            $event = $this->crmGithubWebhookService->handle(
                $payload,
                $rawPayload,
                $request->headers->get('x-github-delivery'),
                $request->headers->get('x-github-event'),
                $request->headers->get('x-hub-signature-256'),
            );
        } catch (HttpException $exception) {
            return new JsonResponse([
                'processed' => false,
                'message' => $exception->getMessage(),
            ], $exception->getStatusCode());
        }

        return new JsonResponse([
            'processed' => true,
            'eventId' => $event->getId(),
            'deliveryId' => $event->getDeliveryId(),
            'event' => $event->getEventName(),
            'status' => $event->getStatus(),
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
