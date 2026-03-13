<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\ApplicationProduct;

use App\General\Application\Message\EntityCreated;
use App\Shop\Application\Service\ProductHydratorService;
use App\Shop\Application\Service\ShopApplicationResolverService;
use App\Shop\Domain\Entity\Product;
use App\Shop\Transport\Controller\Api\V1\Input\Product\CreateProductInput;
use App\Shop\Transport\Controller\Api\V1\Input\Product\ProductInputValidator;
use App\Shop\Transport\Controller\Api\V1\Input\Support\ValidationResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
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
#[OA\Tag(name: 'Shop')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class CreateApplicationProductController
{
    public function __construct(
        private ShopApplicationResolverService $shopApplicationResolverService,
        private ProductHydratorService $productHydratorService,
        private ProductInputValidator $productInputValidator,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws ExceptionInterface
     */
    #[Route('/v1/shop/applications/{applicationSlug}/products', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $shop = $this->shopApplicationResolverService->resolveOrCreateShopByApplicationSlug($applicationSlug);

        try {
            $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ValidationResponseFactory::invalidJson();
        }

        $input = CreateProductInput::fromArray($payload);
        $validationResponse = $this->productInputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $product = $this->productHydratorService->hydrateProduct(new Product()->setShop($shop), $payload);

        $this->entityManager->persist($product);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_product', $product->getId(), context: [
            'applicationSlug' => $applicationSlug,
        ]));

        return new JsonResponse([
            'id' => $product->getId(),
            'shopId' => $shop->getId(),
            'applicationSlug' => $applicationSlug,
        ], JsonResponse::HTTP_CREATED);
    }
}
