<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\General\Application\Message\EntityDeleted;
use App\Shop\Domain\Entity\Product;
use App\Shop\Infrastructure\Repository\ProductRepository;
use App\Shop\Infrastructure\Repository\ShopRepository;
use Doctrine\ORM\EntityManagerInterface;
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
final readonly class DeleteGeneralProductController
{
    public function __construct(
        private ShopRepository $shopRepository,
        private ProductRepository $productRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[OA\Delete(summary: 'Delete product from global shop scope')]
    public function __invoke(string $id): JsonResponse
    {
        $shop = $this->shopRepository->findGlobalShop();
        if ($shop === null) {
            return new JsonResponse(['message' => 'Global shop not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $product = $this->productRepository->findOneByIdAndShop($id, $shop);
        if (!$product instanceof Product) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($product);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('shop_product', $id));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
