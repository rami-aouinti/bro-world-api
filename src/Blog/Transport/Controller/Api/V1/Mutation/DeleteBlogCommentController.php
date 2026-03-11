<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1\Mutation;

use App\Blog\Application\Message\DeleteBlogCommentCommand;
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
final readonly class DeleteBlogCommentController
{
    public function __construct(
        private MessageBusInterface $messageBus
    ) {
    }

    #[Route('/v1/private/blog/comments/{commentId}', methods: [Request::METHOD_DELETE])]
    public function __invoke(string $commentId, User $loggedInUser): JsonResponse
    {
        $this->messageBus->dispatch(new DeleteBlogCommentCommand((string)uniqid('op_', true), $loggedInUser->getId(), $commentId));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
