<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1\Mutation;

use App\Blog\Application\Message\PatchBlogReactionCommand;
use App\Blog\Application\Service\BlogMutationRequestService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Blog')]
final readonly class PatchBlogReactionController
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private BlogMutationRequestService $requestService,
    ) {
    }

    #[Route('/v1/private/blog/reactions/{reactionId}', methods: [Request::METHOD_PATCH])]
    public function __invoke(string $reactionId, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->requestService->extractPayload($request);
        $this->messageBus->dispatch(new PatchBlogReactionCommand((string)uniqid('op_', true), $loggedInUser->getId(), $reactionId, $this->requestService->parseReactionType((string)($payload['type'] ?? 'like'))));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
