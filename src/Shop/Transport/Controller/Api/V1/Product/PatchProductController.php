<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Product;

use App\General\Application\Message\EntityCreated;
use App\Shop\Application\Service\ProductHydratorService;
use App\Shop\Application\Service\ProductListService;
use App\Shop\Domain\Entity\Product;
use App\Shop\Infrastructure\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Shop')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class PatchProductController
{
    public function __construct(
        private ProductRepository $productRepository,
        private ProductHydratorService $productHydratorService,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/shop/{applicationSlug}/products/{id}', methods: [Request::METHOD_PATCH])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, string $id, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $product = $this->productRepository->find($id);
        if (!$product instanceof Product) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = (array)json_decode((string)$request->getContent(), true);
        $this->productHydratorService->hydrateProduct($product, $payload, true);

        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_product', $product->getId()));

        return new JsonResponse(ProductListService::serializeProduct($product));
    }
}
