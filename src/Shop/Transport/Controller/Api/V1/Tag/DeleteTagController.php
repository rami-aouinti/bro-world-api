<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Tag;

use App\General\Application\Message\EntityDeleted;
use App\Shop\Application\Service\ShopApplicationResolverService;
use App\Shop\Domain\Entity\Tag;
use App\Shop\Infrastructure\Repository\TagRepository;
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
final readonly class DeleteTagController
{
    public function __construct(
        private ShopApplicationResolverService $shopApplicationResolverService,
        private TagRepository $tagRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/v1/shop/tags/{id}', methods: [Request::METHOD_DELETE])]
    #[OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, string $id): JsonResponse
    {
        $this->shopApplicationResolverService->resolveOrCreateShopByApplicationSlug($applicationSlug);

        $tag = $this->tagRepository->findOneByIdAndApplicationScope($id, $applicationSlug);
        if (!$tag instanceof Tag) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($tag);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('shop_tag', $id));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
