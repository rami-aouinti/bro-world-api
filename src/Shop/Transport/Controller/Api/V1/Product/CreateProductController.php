<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Product;

use App\General\Application\Message\EntityCreated;
use App\Shop\Application\Service\ProductHydratorService;
use App\Shop\Domain\Entity\Product;
use App\Shop\Infrastructure\Repository\ShopRepository;
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
final readonly class CreateProductController
{
    public function __construct(
        private ProductHydratorService $productHydratorService,
        private ShopRepository $shopRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/shop/{applicationSlug}/products', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $payload = (array)json_decode((string)$request->getContent(), true);
        $product = $this->productHydratorService->hydrateProduct(new Product(), $payload);

        if (is_string($payload['shopId'] ?? null)) {
            $product->setShop($this->shopRepository->find($payload['shopId']));
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_product', $product->getId()));

        return new JsonResponse([
            'id' => $product->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
