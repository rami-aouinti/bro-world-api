<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\ApplicationProduct;

use App\General\Application\Message\EntityCreated;
use App\Shop\Domain\Entity\Product;
use App\Shop\Transport\Controller\Api\V1\Support\ProductPayloadHydrator;
use App\Shop\Transport\Controller\Api\V1\Support\ShopApplicationResolver;
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
final readonly class CreateApplicationProductController
{
    public function __construct(
        private ShopApplicationResolver $shopApplicationResolver,
        private ProductPayloadHydrator $productPayloadHydrator,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/shop/applications/{applicationSlug}/products', methods: [Request::METHOD_POST])]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $shop = $this->shopApplicationResolver->resolveOrCreateShopByApplicationSlug($applicationSlug);
        $payload = (array) json_decode((string) $request->getContent(), true);

        $product = $this->productPayloadHydrator->hydrate((new Product())->setShop($shop), $payload);

        $this->entityManager->persist($product);
        $this->entityManager->flush();
        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageBus->dispatch(new EntityCreated('shop_product', $product->getId(), context: [
            'applicationSlug' => $applicationSlug,
            'operationId' => $operationId,
        ]));

        return new JsonResponse([
            'operationId' => $operationId,
            'shopId' => $shop->getId(),
            'applicationSlug' => $applicationSlug,
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
