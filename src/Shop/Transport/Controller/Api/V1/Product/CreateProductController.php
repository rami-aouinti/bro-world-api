<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Product;

use App\General\Application\Message\EntityCreated;
use App\Shop\Application\Monitoring\ShopMonitoringService;
use App\Shop\Application\Service\ProductHydratorService;
use App\Shop\Application\Service\ShopApplicationResolverService;
use App\Shop\Domain\Entity\Product;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Infrastructure\Repository\ShopRepository;
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
final readonly class CreateProductController
{
    private const LEGACY_ROUTE_WARNING = 'Deprecated endpoint: use /v1/shop/products instead.';
    private const LEGACY_ROUTE_COUNTER = 'shop.legacy_route.usage_total';

    public function __construct(
        private ProductHydratorService $productHydratorService,
        private ProductInputValidator $productInputValidator,
        private ShopApplicationResolverService $shopApplicationResolverService,
        private ShopRepository $shopRepository,
        private ShopMonitoringService $shopMonitoringService,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws ExceptionInterface
     */
    #[Route('/v1/shop/legacy/products', methods: [Request::METHOD_POST])]
    #[OA\Post(
        deprecated: true,
        summary: 'Legacy create product endpoint',
        description: 'Deprecated: migrate to /v1/shop/products.',
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $this->shopMonitoringService->logStructured(
            'shop.legacy_route.used',
            'Legacy shop product create endpoint called.',
            [
                'legacy_route' => true,
                'route' => '/v1/shop/legacy/products',
                'method' => Request::METHOD_POST,
                'replacement' => '/v1/shop/products',
                'sunset_at' => '2026-12-31T23:59:59Z',
                'user_agent' => $request->headers->get('User-Agent'),
            ],
            'info'
        );
        $this->shopMonitoringService->incrementCounter(self::LEGACY_ROUTE_COUNTER, [
            'module' => 'shop',
            'route' => '/v1/shop/legacy/products',
            'method' => Request::METHOD_POST,
        ]);

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

        $shop = $this->resolveShop($input);
        if (!$shop instanceof Shop) {
            return new JsonResponse([
                'message' => 'Validation failed.',
                'errors' => [[
                    'field' => 'shopId',
                    'message' => 'shopId is required when applicationSlug is missing.',
                    'code' => 'SHOP_SCOPE_REQUIRED',
                ]],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $product = $this->productHydratorService->hydrateProduct(new Product()->setShop($shop), $payload);

        $this->entityManager->persist($product);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_product', $product->getId()));

        $response = new JsonResponse([
            'id' => $product->getId(),
        ], JsonResponse::HTTP_CREATED);
        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Sunset', 'Wed, 31 Dec 2026 23:59:59 GMT');
        $response->headers->set('Warning', sprintf('299 - "%s"', self::LEGACY_ROUTE_WARNING));

        return $response;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    private function resolveShop(CreateProductInput $input): ?Shop
    {
        if ($input->applicationSlug !== null && $input->applicationSlug !== '') {
            return $this->shopApplicationResolverService->resolveOrCreateShopByApplicationSlug($input->applicationSlug);
        }

        if ($input->shopId !== null && $input->shopId !== '') {
            $shop = $this->shopRepository->find($input->shopId);

            return $shop instanceof Shop ? $shop : null;
        }

        return null;
    }
}
