<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1\Mutation;

use App\Blog\Application\Message\CreateGeneralBlogCommand;
use App\Blog\Application\Service\BlogMutationRequestService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Blog')]
final readonly class CreateGeneralBlogController
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private BlogMutationRequestService $requestService,
        private Security $security,
    ) {
    }

    #[Route('/v1/private/blogs/general', methods: [Request::METHOD_POST])]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !in_array('ROLE_ROOT', $user->getRoles(), true)) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only root can create General blog.');
        }

        $payload = $this->requestService->extractPayload($request);
        $this->messageBus->dispatch(new CreateGeneralBlogCommand((string)uniqid('op_', true), $user->getId(), (string)($payload['title'] ?? 'General Blog'), isset($payload['description']) ? (string)$payload['description'] : null));

        return new JsonResponse([
            'status' => 'accepted',
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
