<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Tag;

use App\General\Application\Message\EntityCreated;
use App\Shop\Application\Service\ShopApplicationResolverService;
use App\Shop\Domain\Entity\Tag;
use App\Shop\Domain\Enum\TagType;
use Doctrine\ORM\EntityManagerInterface;
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
final readonly class CreateTagController
{
    public function __construct(
        private ShopApplicationResolverService $shopApplicationResolverService,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/shop/applications/{applicationSlug}/tags', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $this->shopApplicationResolverService->resolveOrCreateShopByApplicationSlug($applicationSlug);

        $payload = (array)json_decode((string)$request->getContent(), true);
        $tag = (new Tag())
            ->setLabel((string)($payload['label'] ?? ''))
            ->setType(TagType::tryFrom((string)($payload['type'] ?? '')) ?? TagType::MARKETING);

        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_tag', $tag->getId()));

        return new JsonResponse([
            'id' => $tag->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
