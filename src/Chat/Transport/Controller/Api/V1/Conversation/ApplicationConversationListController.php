<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Conversation;

use App\Chat\Application\Service\ConversationListService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Chat Conversation')]
#[OA\Get(
    path: '/v1/chat/chats/{chatId}/conversations',
    operationId: 'chat_conversation_public_chat_list',
    summary: "Lister les conversations d'un chat",
    tags: ['Chat Conversation'],
    parameters: [
        new OA\Parameter(name: 'chatId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
        new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)),
        new OA\Parameter(name: 'message', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'bonjour')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Liste paginée des conversations'),
    ]
)]
class ApplicationConversationListController
{
    public function __construct(private readonly ConversationListService $conversationListService)
    {
    }

    #[Route(path: '/v1/chat/chats/{chatId}/conversations', methods: [Request::METHOD_GET])]
    public function __invoke(string $chatId, Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'message' => trim((string) $request->query->get('message', '')),
        ];

        return ConversationJsonResponseFactory::create(
            $this->conversationListService->getByChatId($chatId, $filters, $page, $limit)
        );
    }
}
