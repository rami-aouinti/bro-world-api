<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\General\Application\Message\EntityCreated;
use App\Shop\Application\Service\SlugBuilderService;
use App\Shop\Domain\Entity\Category;
use App\Shop\Infrastructure\Repository\CategoryRepository;
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
final readonly class PatchGeneralCategoryController
{
    public function __construct(
        private ShopRepository $shopRepository,
        private CategoryRepository $categoryRepository,
        private SlugBuilderService $slugBuilderService,
        private CategoryInputValidator $categoryInputValidator,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/shop/general/categories/{id}', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'Patch category in global shop scope')]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $globalShop = $this->shopRepository->findGlobalShop();
        if ($globalShop === null) {
            return new JsonResponse(['message' => 'Global shop not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $category = $this->categoryRepository->findOneByIdAndShop($id, $globalShop);
        if (!$category instanceof Category) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
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

        $category
            ->setName($input->name)
            ->setSlug($this->slugBuilderService->buildSlug((string)($input->slug ?? $input->name)))
            ->setDescription($input->description)
            ->setPhoto((string)($input->photo ?? ''));

        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_category', $category->getId()));

        return new JsonResponse(['category' => [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'description' => $category->getDescription(),
            'photo' => $category->getPhoto(),
        ]]);
    }
}
