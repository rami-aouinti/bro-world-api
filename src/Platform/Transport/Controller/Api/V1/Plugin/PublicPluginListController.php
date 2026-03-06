<?php

declare(strict_types=1);

namespace App\Platform\Transport\Controller\Api\V1\Plugin;

use App\General\Transport\Rest\ResponseHandler;
use App\Platform\Application\Resource\PluginResource;
use App\Platform\Domain\Entity\Plugin;
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
#[OA\Tag(name: 'Plugin')]
class PublicPluginListController
{
    public function __construct(
        private readonly PluginResource $pluginResource,
        private readonly ResponseHandler $responseHandler,
    ) {
    }

    /**
     * @throws Throwable
     */
    #[Route(
        path: '/v1/plugin/public',
        methods: [Request::METHOD_GET],
    )]
    #[OA\Get(
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of enabled public plugins.',
                content: new JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        ref: new Model(
                            type: Plugin::class,
                            groups: ['Plugin.id', 'Plugin.name', 'Plugin.description', 'Plugin.pluginKey', 'Plugin.photo'],
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
            data: $this->pluginResource->findPublicEnabled(),
            restResource: $this->pluginResource,
            context: [
                'groups' => ['Plugin.id', 'Plugin.name', 'Plugin.description', 'Plugin.pluginKey', 'Plugin.photo'],
            ],
        );
    }
}
