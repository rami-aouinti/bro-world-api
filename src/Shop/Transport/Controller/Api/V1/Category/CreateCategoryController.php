<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Category;

use App\General\Application\Message\EntityCreated;
use App\Shop\Application\Service\ShopApplicationResolverService;
use App\Shop\Application\Service\SlugBuilderService;
use App\Shop\Domain\Entity\Category;
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
final readonly class CreateCategoryController
{
    public function __construct(
        private ShopApplicationResolverService $shopApplicationResolverService,
        private SlugBuilderService $slugBuilderService,
        private CategoryInputValidator $categoryInputValidator,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/shop/categories', methods: [Request::METHOD_POST])]
        public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $shop = $this->shopApplicationResolverService->resolveOrCreateShopByApplicationSlug($applicationSlug);

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

        $category = new Category()
            ->setShop($shop)
            ->setName($input->name)
            ->setSlug($this->slugBuilderService->buildSlug((string)($input->slug ?? $input->name)))
            ->setDescription($input->description)
            ->setPhoto((string)($input->photo ?? ''));

        $this->entityManager->persist($category);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_category', $category->getId()));

        return new JsonResponse([
            'id' => $category->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
