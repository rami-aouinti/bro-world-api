<?php

declare(strict_types=1);

namespace App\Chat\Application\Service;

use App\Chat\Domain\Entity\ChatMessage;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Domain\Repository\Interfaces\ConversationRepositoryInterface;
use App\General\Application\Service\CacheKeyConventionService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\User\Domain\Entity\User;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Throwable;

use function array_filter;
use function array_map;

final readonly class ConversationListService
{
    public function __construct(
        private ConversationRepositoryInterface $conversationRepository,
        private CacheInterface $cache,
        private ElasticsearchServiceInterface $elasticsearchService,
        private CacheKeyConventionService $cacheKeyConventionService,
    ) {
    }

    /**
     * @param array<string, string> $filters
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function getByUser(User $user, array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->getList('user', $filters, $page, $limit, $user, null);
    }

    /**
     * @param array<string, string> $filters
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function getByChatId(string $chatId, array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->getList('chat_public', $filters, $page, $limit, null, $chatId);
    }

    /**
     * @param array<string, string> $filters
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function getByChatIdAndUser(string $chatId, User $user, array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->getList('chat_private', $filters, $page, $limit, $user, $chatId);
    }

    /**
     * @param array<string, string> $filters
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function getList(string $accessContext, array $filters, int $page, int $limit, ?User $user, ?string $chatId): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $filters = [
            'message' => trim((string)($filters['message'] ?? '')),
        ];

        $cachePayload = [
            'accessContext' => $accessContext,
            'userId' => $user?->getId(),
            'chatId' => $chatId,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ];

        $cacheKey = $user !== null
            ? $this->cacheKeyConventionService->buildPrivateConversationKey($user->getUsername(), $cachePayload)
            : 'conversation_list_' . md5((string)json_encode($cachePayload, JSON_THROW_ON_ERROR));

        /** @var array<string, mixed> $result */
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($accessContext, $user, $chatId, $filters, $page, $limit): array {
            $item->expiresAfter(120);
            if ($user !== null && method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->tagPrivateConversation($user->getId()));
            }
            if ($chatId !== null && $chatId !== '' && method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->tagPublicConversationByChat($chatId));
            }

            $esIds = $this->searchIdsFromElastic($filters);
            if ($esIds === []) {
                return [
                    'items' => [],
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'totalItems' => 0,
                        'totalPages' => 0,
                    ],
                ];
            }

            if ($accessContext === 'user') {
                $conversations = $this->conversationRepository->findByUser($user, $filters, $page, $limit, $esIds);
                $totalItems = $this->conversationRepository->countByUser($user, $filters, $esIds);
            } elseif ($accessContext === 'chat_private') {
                $conversations = $this->conversationRepository->findByChatIdAndUser($chatId, $user, $filters, $page, $limit, $esIds);
                $totalItems = $this->conversationRepository->countByChatIdAndUser($chatId, $user, $filters, $esIds);
            } else {
                $conversations = $this->conversationRepository->findByChatId($chatId, $filters, $page, $limit, $esIds);
                $totalItems = $this->conversationRepository->countByChatId($chatId, $filters, $esIds);
            }

            return [
                'items' => $this->normalizeConversations($conversations, $user),
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'totalItems' => $totalItems,
                    'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0,
                ],
            ];
        });

        $result['filters'] = array_filter($filters, static fn (string $value): bool => $value !== '');

        return $result;
    }

    /**
     * @param array<string, string> $filters
     *
     * @return array<int, string>|null
     */
    private function searchIdsFromElastic(array $filters): ?array
    {
        if ($filters['message'] === '') {
            return null;
        }

        try {
            $response = $this->elasticsearchService->search(
                ElasticsearchServiceInterface::INDEX_PREFIX . '_*',
                [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'match_phrase_prefix' => [
                                        'message' => $filters['message'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '_source' => ['id'],
                ],
                0,
                1000,
            );

            if (!is_array($response) || !isset($response['hits']['hits']) || !is_array($response['hits']['hits'])) {
                return null;
            }

            $ids = [];
            foreach ($response['hits']['hits'] as $hit) {
                if (is_array($hit) && isset($hit['_source']['id']) && is_string($hit['_source']['id'])) {
                    $ids[] = $hit['_source']['id'];
                }
            }

            return array_values(array_unique($ids));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<int, Conversation> $conversations
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeConversations(array $conversations, ?User $connectedUser): array
    {
        $connectedUserId = $connectedUser?->getId();

        return array_map(function (Conversation $conversation) use ($connectedUserId): array {
            $connectedParticipant = null;
            if ($connectedUserId !== null) {
                foreach ($conversation->getParticipants() as $participant) {
                    if ($participant->getUser()->getId() === $connectedUserId) {
                        $connectedParticipant = $participant;
                        break;
                    }
                }
            }

            $lastReadMessageAt = $connectedParticipant?->getLastReadMessageAt();
            $visibleMessages = array_values(array_filter(
                $conversation->getMessages()->toArray(),
                static fn (ChatMessage $message): bool => $message->getDeletedAt() === null
            ));

            return [
                'id' => $conversation->getId(),
                'chatId' => $conversation->getChat()?->getId(),
                'type' => $conversation->getType()->value,
                'title' => $conversation->getTitle(),
                'participants' => array_map(
                    function (ConversationParticipant $participant) use ($connectedUserId): array {
                        $participantUser = $participant->getUser();
                        $participantUserId = $participantUser?->getId();

                        return [
                            'id' => $participant->getId(),
                            'user' => [
                                'id' => $participantUserId,
                                'firstName' => $participantUser?->getFirstName(),
                                'lastName' => $participantUser?->getLastName(),
                                'photo' => $participantUser?->getPhoto(),
                                'owner' => $connectedUserId !== null && $participantUserId === $connectedUserId,
                            ],
                        ];
                    },
                    $conversation->getParticipants()->toArray()
                ),
                'unreadMessagesCount' => array_reduce(
                    $visibleMessages,
                    static function (int $carry, ChatMessage $message) use ($connectedUserId, $lastReadMessageAt): int {
                        if ($connectedUserId === null) {
                            return $carry;
                        }

                        $isOwner = $message->getSender()->getId() === $connectedUserId;
                        if ($isOwner || ($lastReadMessageAt !== null && $message->getCreatedAt() <= $lastReadMessageAt)) {
                            return $carry;
                        }

                        return $carry + 1;
                    },
                    0
                ),
                'lastMessage' => $this->normalizeLastMessage($visibleMessages, $connectedUserId),
                'lastMessageAt' => $conversation->getLastMessageAt()?->format(DATE_ATOM),
                'archivedAt' => $conversation->getArchivedAt()?->format(DATE_ATOM),
                'createdAt' => $conversation->getCreatedAt()?->format(DATE_ATOM),
            ];
        }, $conversations);
    }

    /**
     * @param array<int, ChatMessage> $visibleMessages
     *
     * @return array<string, mixed>|null
     */
    private function normalizeLastMessage(array $visibleMessages, ?string $connectedUserId): ?array
    {
        if ($visibleMessages === []) {
            return null;
        }

        $lastMessage = $visibleMessages[array_key_last($visibleMessages)];
        $sender = $lastMessage->getSender();
        $senderId = $sender?->getId();

        return [
            'id' => $lastMessage->getId(),
            'content' => mb_substr((string)$lastMessage->getContent(), 0, 280),
            'createdAt' => $lastMessage->getCreatedAt()?->format(DATE_ATOM),
            'sender' => [
                'id' => $senderId,
                'firstName' => $sender?->getFirstName(),
                'lastName' => $sender?->getLastName(),
                'photo' => $sender?->getPhoto(),
                'owner' => $connectedUserId !== null && $senderId === $connectedUserId,
            ],
        ];
    }
}
