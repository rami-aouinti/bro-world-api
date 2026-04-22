<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Product;

use App\General\Domain\Service\Interfaces\MessageServiceInterface;
use App\Shop\Application\Message\DeleteProductCommand;
use App\Shop\Application\Service\ShopApplicationResolverService;
use App\Shop\Domain\Entity\Product;
use App\Shop\Infrastructure\Repository\ProductRepository;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;
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
    public function __construct(
        private ShopApplicationResolverService $shopApplicationResolverService,
        private ProductRepository $productRepository,
        private MessageServiceInterface $messageService,
    ) {
    }

    /**
     * @throws \Throwable
     */
    #[Route('/v1/shop/products/{id}', methods: [Request::METHOD_DELETE])]
    #[OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, string $id): JsonResponse
    {
        $shop = $this->shopApplicationResolverService->resolveOrCreateShopByApplicationSlug($applicationSlug);
        $product = $this->productRepository->findOneByIdAndShop($id, $shop);
        if (!$product instanceof Product) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        $operationId = Uuid::uuid4()->toString();
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
