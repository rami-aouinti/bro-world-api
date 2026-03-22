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
#[OA\Tag(name: 'Crm')]
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
    #[OA\Post(
        description: 'Use this endpoint as GitHub webhook URL. In /api/doc, click "Try it out", set required headers and paste the raw GitHub payload JSON.',
        summary: 'Public GitHub webhook endpoint with HMAC signature validation + idempotence guard.',
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                example: [
                    'action' => 'opened',
                    'repository' => [
                        'full_name' => 'rami-aouinti/bro-world-api',
                    ],
                    'issue' => [
                        'number' => 42,
                        'title' => 'Bug webhook CRM',
                    ],
                ],
            ),
        ),
    )]
    #[OA\Parameter(name: 'x-github-delivery', in: 'header', required: true, schema: new OA\Schema(type: 'string'), example: '7f5baf30-6402-11ef-8946-3f95d8cf7f6c')]
    #[OA\Parameter(name: 'x-github-event', in: 'header', required: true, schema: new OA\Schema(type: 'string'), example: 'issues')]
    #[OA\Parameter(name: 'x-hub-signature-256', in: 'header', required: true, schema: new OA\Schema(type: 'string'), example: 'sha256=4f7f2e11f2f6c477f2c5f8cf227f4e5b2e02691a1f4a5e80ccfe84fc8c3fd6c2')]
    #[OA\Response(
        response: JsonResponse::HTTP_ACCEPTED,
        description: 'Webhook accepted and queued/processed.',
        content: new OA\JsonContent(
            example: [
                'processed' => true,
                'eventId' => 'f53d5a41-9f56-4f2c-a9f3-f8a2fd17f12e',
                'deliveryId' => '7f5baf30-6402-11ef-8946-3f95d8cf7f6c',
                'event' => 'issues',
                'status' => 'processed',
            ],
        ),
    )]
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
