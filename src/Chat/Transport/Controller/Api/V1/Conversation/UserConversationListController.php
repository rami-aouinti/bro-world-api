<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Conversation;

use App\Chat\Application\Service\ConversationListService;
use App\User\Domain\Entity\User;
use JsonException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Chat Conversation')]
#[OA\Get(
    path: '/v1/chat/private/conversations',
    operationId: 'chat_conversation_private_list',
    summary: 'Lister les conversations de l\'utilisateur connecté',
    tags: ['Chat Conversation'],
    parameters: [
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
        new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1)),
        new OA\Parameter(name: 'message', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'bonjour')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Liste paginée des conversations'),
    ]
)]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class UserConversationListController
{
    public function __construct(
        private ConversationListService $conversationListService
    ) {
    }

    /**
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    #[Route(path: '/v1/chat/private/conversations', methods: [Request::METHOD_GET])]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'message' => trim((string)$request->query->get('message', '')),
        ];

        return ConversationJsonResponseFactory::create($this->conversationListService->getByUser($loggedInUser, $filters, $page, $limit));
    }
}
