<?php

declare(strict_types=1);

namespace App\Game\Transport\Controller\Api\V1;

use App\Game\Application\DTO\GameCategoryResponseDto;
use App\Game\Application\DTO\GameLevelResponseDto;
use App\Game\Domain\Entity\GameCategory;
use App\Game\Domain\Entity\GameLevelOption;
use App\Game\Domain\Enum\GameLevel;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Game')]
final readonly class GameTaxonomyController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('/v1/game-categories', methods: [Request::METHOD_GET])]
    public function categories(): JsonResponse
    {
        $categories = $this->entityManager->getRepository(GameCategory::class)->findBy([], ['nameKey' => 'ASC']);

        return new JsonResponse([
            'items' => array_map(
                static fn (GameCategory $category): array => GameCategoryResponseDto::fromEntity($category)->toArray(),
                $categories,
            ),
        ]);
    }

    #[Route('/v1/game-categories/{id}', methods: [Request::METHOD_GET])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    public function categoryDetail(string $id): JsonResponse
    {
        $category = $this->entityManager->getRepository(GameCategory::class)->find($id);
        if (!$category instanceof GameCategory) {
            return new JsonResponse(['message' => 'Game category not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse(GameCategoryResponseDto::fromEntity($category)->toArray());
    }

    #[Route('/v1/game-categories', methods: [Request::METHOD_POST])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    public function createCategory(Request $request): JsonResponse
    {
        $payload = $this->decodeRequest($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $name = trim((string)($payload['name'] ?? ''));
        $key = strtolower(trim((string)($payload['key'] ?? '')));
        $description = trim((string)($payload['description'] ?? ''));

        if ($name === '' || $key === '') {
            return new JsonResponse([
                'message' => 'Validation failed.',
                'errors' => ['name and key are required.'],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $exists = $this->entityManager->getRepository(GameCategory::class)->findOneBy(['key' => $key]);
        if ($exists instanceof GameCategory) {
            return new JsonResponse(['message' => 'Game category key already exists.'], JsonResponse::HTTP_CONFLICT);
        }

        $category = (new GameCategory())
            ->setName($name)
            ->setKey($key)
            ->setDescription($description);

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return new JsonResponse(GameCategoryResponseDto::fromEntity($category)->toArray(), JsonResponse::HTTP_CREATED);
    }

    #[Route('/v1/game-categories/{id}', methods: [Request::METHOD_PUT, Request::METHOD_PATCH])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    public function updateCategory(string $id, Request $request): JsonResponse
    {
        $category = $this->entityManager->getRepository(GameCategory::class)->find($id);
        if (!$category instanceof GameCategory) {
            return new JsonResponse(['message' => 'Game category not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decodeRequest($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if (array_key_exists('name', $payload)) {
            $name = trim((string)$payload['name']);
            if ($name === '') {
                return new JsonResponse(['message' => 'Validation failed.', 'errors' => ['name cannot be empty.']], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $category->setName($name);
        }

        if (array_key_exists('key', $payload)) {
            $key = strtolower(trim((string)$payload['key']));
            if ($key === '') {
                return new JsonResponse(['message' => 'Validation failed.', 'errors' => ['key cannot be empty.']], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $exists = $this->entityManager->getRepository(GameCategory::class)->findOneBy(['key' => $key]);
            if ($exists instanceof GameCategory && $exists->getId() !== $category->getId()) {
                return new JsonResponse(['message' => 'Game category key already exists.'], JsonResponse::HTTP_CONFLICT);
            }
            $category->setKey($key);
        }

        if (array_key_exists('description', $payload)) {
            $category->setDescription(trim((string)$payload['description']));
        }

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return new JsonResponse(GameCategoryResponseDto::fromEntity($category)->toArray());
    }

    #[Route('/v1/game-categories/{id}', methods: [Request::METHOD_DELETE])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    public function deleteCategory(string $id): JsonResponse
    {
        $category = $this->entityManager->getRepository(GameCategory::class)->find($id);
        if (!$category instanceof GameCategory) {
            return new JsonResponse(['message' => 'Game category not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/game-levels', methods: [Request::METHOD_GET])]
    public function levels(): JsonResponse
    {
        $levels = $this->entityManager->getRepository(GameLevelOption::class)->findBy([], ['value' => 'ASC']);

        return new JsonResponse([
            'items' => array_map(
                static fn (GameLevelOption $level): array => GameLevelResponseDto::fromEntity($level)->toArray(),
                $levels,
            ),
        ]);
    }

    #[Route('/v1/game-levels/{id}', methods: [Request::METHOD_GET])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    public function levelDetail(string $id): JsonResponse
    {
        $level = $this->entityManager->getRepository(GameLevelOption::class)->find($id);
        if (!$level instanceof GameLevelOption) {
            return new JsonResponse(['message' => 'Game level not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse(GameLevelResponseDto::fromEntity($level)->toArray());
    }

    #[Route('/v1/game-levels', methods: [Request::METHOD_POST])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    public function createLevel(Request $request): JsonResponse
    {
        $payload = $this->decodeRequest($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $value = strtoupper(trim((string)($payload['value'] ?? '')));
        $label = trim((string)($payload['label'] ?? ''));
        $description = trim((string)($payload['description'] ?? ''));

        if ($value === '' || $label === '') {
            return new JsonResponse([
                'message' => 'Validation failed.',
                'errors' => ['value and label are required.'],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (GameLevel::tryFrom($value) === null) {
            return new JsonResponse(['message' => 'value must be a valid GameLevel enum value.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $exists = $this->entityManager->getRepository(GameLevelOption::class)->findOneBy(['value' => $value]);
        if ($exists instanceof GameLevelOption) {
            return new JsonResponse(['message' => 'Game level value already exists.'], JsonResponse::HTTP_CONFLICT);
        }

        $level = (new GameLevelOption())
            ->setValue($value)
            ->setLabel($label)
            ->setDescription($description);

        $this->entityManager->persist($level);
        $this->entityManager->flush();

        return new JsonResponse(GameLevelResponseDto::fromEntity($level)->toArray(), JsonResponse::HTTP_CREATED);
    }

    #[Route('/v1/game-levels/{id}', methods: [Request::METHOD_PUT, Request::METHOD_PATCH])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    public function updateLevel(string $id, Request $request): JsonResponse
    {
        $level = $this->entityManager->getRepository(GameLevelOption::class)->find($id);
        if (!$level instanceof GameLevelOption) {
            return new JsonResponse(['message' => 'Game level not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decodeRequest($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if (array_key_exists('value', $payload)) {
            $value = strtoupper(trim((string)$payload['value']));
            if ($value === '' || GameLevel::tryFrom($value) === null) {
                return new JsonResponse(['message' => 'value must be a valid GameLevel enum value.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $exists = $this->entityManager->getRepository(GameLevelOption::class)->findOneBy(['value' => $value]);
            if ($exists instanceof GameLevelOption && $exists->getId() !== $level->getId()) {
                return new JsonResponse(['message' => 'Game level value already exists.'], JsonResponse::HTTP_CONFLICT);
            }
            $level->setValue($value);
        }

        if (array_key_exists('label', $payload)) {
            $label = trim((string)$payload['label']);
            if ($label === '') {
                return new JsonResponse(['message' => 'Validation failed.', 'errors' => ['label cannot be empty.']], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $level->setLabel($label);
        }

        if (array_key_exists('description', $payload)) {
            $level->setDescription(trim((string)$payload['description']));
        }

        $this->entityManager->persist($level);
        $this->entityManager->flush();

        return new JsonResponse(GameLevelResponseDto::fromEntity($level)->toArray());
    }

    #[Route('/v1/game-levels/{id}', methods: [Request::METHOD_DELETE])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    public function deleteLevel(string $id): JsonResponse
    {
        $level = $this->entityManager->getRepository(GameLevelOption::class)->find($id);
        if (!$level instanceof GameLevelOption) {
            return new JsonResponse(['message' => 'Game level not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($level);
        $this->entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string,mixed>|JsonResponse
     */
    private function decodeRequest(Request $request): array|JsonResponse
    {
        try {
            return (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new JsonResponse([
                'message' => 'Invalid JSON payload.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }
    }
}
