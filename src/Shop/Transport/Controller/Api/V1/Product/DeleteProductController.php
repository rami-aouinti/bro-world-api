<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Product;

use App\General\Domain\Service\Interfaces\MessageServiceInterface;
use App\Shop\Application\Message\DeleteProductCommand;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Shop')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class DeleteProductController
{
    public function __construct(private MessageServiceInterface $messageService)
    {
    }

    #[Route('/v1/shop/products/{id}', methods: [Request::METHOD_DELETE])]
    public function __invoke(string $id): JsonResponse
    {
        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageService->sendMessage(new DeleteProductCommand(
            operationId: $operationId,
            productId: $id,
        ));

        return new JsonResponse([
            'operationId' => $operationId,
            'id' => $id,
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
