<?php

declare(strict_types=1);

namespace App\Platform\Transport\Controller\Api\V1\Platform;

use App\General\Transport\Rest\ResponseHandler;
use App\Platform\Application\Resource\PlatformResource;
use App\Platform\Domain\Entity\Platform;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\JsonContent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * @package App\Platform
 */
#[AsController]
#[OA\Tag(name: 'Platform')]
class PublicPlatformListController
{
    public function __construct(
        private readonly PlatformResource $platformResource,
        private readonly ResponseHandler $responseHandler,
    ) {
    }

    /**
     * @throws Throwable
     */
    #[Route(
        path: '/v1/platform/public',
        methods: [Request::METHOD_GET],
    )]
    #[OA\Get(
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of enabled public platforms.',
                content: new JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        ref: new Model(
                            type: Platform::class,
                            groups: ['Platform.id', 'Platform.name', 'Platform.description', 'Platform.platformKey', 'Platform.photo'],
                        ),
                    ),
                ),
            ),
        ],
    )]
    public function __invoke(Request $request): Response
    {
        return $this->responseHandler->createResponse(
            request: $request,
            data: $this->platformResource->findPublicEnabled(),
            restResource: $this->platformResource,
            context: [
                'groups' => ['Platform.id', 'Platform.name', 'Platform.description', 'Platform.platformKey', 'Platform.photo'],
            ],
        );
    }
}
