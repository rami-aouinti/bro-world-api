<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1\Mutation;

use App\Blog\Application\Message\DeleteBlogReactionCommand;
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
final readonly class DeleteBlogReactionController
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/v1/private/blog/reactions/{reactionId}', methods: [Request::METHOD_DELETE])]
    public function __invoke(string $reactionId, User $loggedInUser): JsonResponse
    {
        $this->messageBus->dispatch(new DeleteBlogReactionCommand((string)uniqid('op_', true), $loggedInUser->getId(), $reactionId));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
