<?php

declare(strict_types=1);

namespace App\Platform\Transport\Controller\Api\V1\Plugin;

use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\ResponseHandler;
use App\General\Transport\Rest\Traits\Actions;
use App\Platform\Application\DTO\Plugin\PluginCreate;
use App\Platform\Application\DTO\Plugin\PluginPatch;
use App\Platform\Application\DTO\Plugin\PluginUpdate;
use App\Platform\Application\Resource\PluginResource;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @package App\Platform
 *
 * @method PluginResource getResource()
 * @method ResponseHandler getResponseHandler()
 */
#[AsController]
#[Route(
    path: '/v1/plugin',
)]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Plugin Management')]
class PluginController extends Controller
{
    use Actions\Root\CountAction;
    use Actions\Root\FindAction;
    use Actions\Root\FindOneAction;
    use Actions\Root\IdsAction;
    use Actions\Root\CreateAction;
    use Actions\Root\DeleteAction;
    use Actions\Root\PatchAction;
    use Actions\Root\UpdateAction;

    /**
     * @var array<string, string>
     */
    protected static array $dtoClasses = [
        Controller::METHOD_CREATE => PluginCreate::class,
        Controller::METHOD_UPDATE => PluginUpdate::class,
        Controller::METHOD_PATCH => PluginPatch::class,
    ];

    public function __construct(
        PluginResource $resource,
    ) {
        parent::__construct($resource);
    }
}
