<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\General\Application\Message\EntityCreated;
use App\Shop\Application\Service\SlugBuilderService;
use App\Shop\Domain\Entity\Category;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Infrastructure\Repository\ShopRepository;
use App\Shop\Transport\Controller\Api\V1\Input\Category\CategoryInputValidator;
use App\Shop\Transport\Controller\Api\V1\Input\Category\CreateCategoryInput;
use App\Shop\Transport\Controller\Api\V1\Input\Support\ValidationResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
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
final readonly class CreateGeneralCategoryController
{
    public function __construct(
        private ShopRepository $shopRepository,
        private SlugBuilderService $slugBuilderService,
        private CategoryInputValidator $categoryInputValidator,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/shop/general/categories', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Create category in global shop scope')]
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

        $input = CreateCategoryInput::fromArray($payload);
        $validationResponse = $this->categoryInputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $category = (new Category())
            ->setShop($shop)
            ->setName($input->name)
            ->setSlug($this->slugBuilderService->buildSlug((string)($input->slug ?? $input->name)))
            ->setDescription($input->description)
            ->setPhoto((string)($input->photo ?? ''));

        $this->entityManager->persist($category);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_category', $category->getId()));

        return new JsonResponse(['id' => $category->getId()], JsonResponse::HTTP_CREATED);
    }
}
