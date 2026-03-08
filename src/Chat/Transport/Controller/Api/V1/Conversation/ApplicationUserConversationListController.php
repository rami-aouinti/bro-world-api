<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Conversation;

use App\Chat\Application\Service\ConversationListService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Chat Conversation')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class ApplicationUserConversationListController
{
    public function __construct(private readonly ConversationListService $conversationListService)
    {
    }

    #[Route(path: '/v1/chat/private/chats/{chatId}/conversations', methods: [Request::METHOD_GET])]
    public function __invoke(string $chatId, Request $request, User $loggedInUser): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'message' => trim((string) $request->query->get('message', '')),
        ];

        return ConversationJsonResponseFactory::create(
            $this->conversationListService->getByChatIdAndUser($chatId, $loggedInUser, $filters, $page, $limit)
        );
    }
}
