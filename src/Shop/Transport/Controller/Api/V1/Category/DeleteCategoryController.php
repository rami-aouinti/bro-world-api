<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Category;

use App\General\Application\Message\EntityDeleted;
use App\Shop\Application\Service\ShopApplicationResolverService;
use App\Shop\Domain\Entity\Category;
use App\Shop\Infrastructure\Repository\CategoryRepository;
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
final readonly class DeleteCategoryController
{
    public function __construct(
        private ShopApplicationResolverService $shopApplicationResolverService,
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/v1/shop/categories/{id}', methods: [Request::METHOD_DELETE])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, string $id): JsonResponse
    {
        $shop = $this->shopApplicationResolverService->resolveOrCreateShopByApplicationSlug($applicationSlug);
        $category = $this->categoryRepository->findOneByIdAndShop($id, $shop);
        if (!$category instanceof Category) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('shop_category', $id));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
