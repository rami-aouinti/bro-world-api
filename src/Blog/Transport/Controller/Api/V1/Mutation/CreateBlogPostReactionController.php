<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1\Mutation;

use App\Blog\Application\Message\CreateBlogPostReactionCommand;
use App\Blog\Application\Service\BlogMutationRequestService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Blog')]
final readonly class CreateBlogPostReactionController
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private BlogMutationRequestService $requestService,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/v1/private/blog/posts/{postId}/reactions', methods: [Request::METHOD_POST])]
    public function __invoke(string $postId, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->requestService->extractPayload($request);
        $this->messageBus->dispatch(new CreateBlogPostReactionCommand((string)uniqid('op_', true), $loggedInUser->getId(), $postId, $this->requestService->parseReactionType((string)($payload['type'] ?? 'like'))));

        return new JsonResponse([
            'status' => 'accepted',
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
