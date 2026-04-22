<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\General\Application\Message\EntityCreated;
use App\Shop\Application\Service\ProductHydratorService;
use App\Shop\Domain\Entity\Product;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Infrastructure\Repository\ShopRepository;
use App\Shop\Transport\Controller\Api\V1\Input\Product\CreateProductInput;
use App\Shop\Transport\Controller\Api\V1\Input\Product\ProductInputValidator;
use App\Shop\Transport\Controller\Api\V1\Input\Support\ValidationResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
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
final readonly class CreateGeneralProductController
{
    public function __construct(
        private ShopRepository $shopRepository,
        private ProductHydratorService $productHydratorService,
        private ProductInputValidator $productInputValidator,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[OA\Post(summary: 'Create product in global shop scope')]
    public function __invoke(Request $request): JsonResponse
    {
        $shop = $this->shopRepository->findGlobalShop();
        if (!$shop instanceof Shop) {
            return new JsonResponse(['message' => 'Global shop not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

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
        $this->messageBus->dispatch(new EntityCreated('shop_product', $product->getId()));

        return new JsonResponse(['id' => $product->getId()], JsonResponse::HTTP_CREATED);
    }
}
