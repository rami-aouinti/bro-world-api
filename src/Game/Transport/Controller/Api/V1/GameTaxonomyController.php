<?php

declare(strict_types=1);

namespace App\Game\Transport\Controller\Api\V1;

use App\Game\Application\DTO\GameCategoryResponseDto;
use App\Game\Domain\Entity\GameCategory;
use App\Game\Domain\Enum\GameLevel;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Game')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class GameTaxonomyController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('/v1/game-categories', methods: [Request::METHOD_GET])]
    public function categories(): JsonResponse
    {
        $categories = $this->entityManager->getRepository(GameCategory::class)->findBy([], ['name' => 'ASC']);

        return new JsonResponse([
            'items' => array_map(
                static fn (GameCategory $category): array => GameCategoryResponseDto::fromEntity($category)->toArray(),
                $categories,
            ),
        ]);
    }

    #[Route('/v1/game-levels', methods: [Request::METHOD_GET])]
    public function levels(): JsonResponse
    {
        return new JsonResponse([
            'items' => array_map(
                static fn (GameLevel $level): array => [
                    'value' => $level->value,
                    'label' => ucfirst(strtolower($level->value)),
                ],
                GameLevel::cases(),
            ),
        ]);
    }
}
