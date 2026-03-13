<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Tag;

use App\General\Application\Message\EntityCreated;
use App\Shop\Application\Service\ShopApplicationResolverService;
use App\Shop\Domain\Entity\Tag;
use App\Shop\Domain\Enum\TagType;
use App\Shop\Transport\Controller\Api\V1\Input\Support\ValidationResponseFactory;
use App\Shop\Transport\Controller\Api\V1\Input\Tag\CreateTagInput;
use App\Shop\Transport\Controller\Api\V1\Input\Tag\TagInputValidator;
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
final readonly class CreateTagController
{
    public function __construct(
        private ShopApplicationResolverService $shopApplicationResolverService,
        private TagInputValidator $tagInputValidator,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/v1/shop/applications/{applicationSlug}/tags', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $this->shopApplicationResolverService->resolveOrCreateShopByApplicationSlug($applicationSlug);

        try {
            $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ValidationResponseFactory::invalidJson();
        }

        $input = CreateTagInput::fromArray($payload);
        $validationResponse = $this->tagInputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $tag = new Tag()
            ->setLabel($input->label)
            ->setType(TagType::tryFrom((string)($input->type ?? '')) ?? TagType::MARKETING);

        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('shop_tag', $tag->getId()));

        return new JsonResponse([
            'id' => $tag->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
