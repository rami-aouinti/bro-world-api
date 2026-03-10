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
#[OA\Get(path: '/v1/plugin', operationId: 'plugin_list', summary: 'Lister les plugins', tags: ['Plugin Management'], parameters: [new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)), new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 20))], responses: [new OA\Response(response: 200, description: 'Liste des plugins')])]
#[OA\Post(path: '/v1/plugin', operationId: 'plugin_create', summary: 'Créer un plugin', tags: ['Plugin Management'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name', 'description', 'private', 'enabled'], properties: [new OA\Property(property: 'name', type: 'string', minLength: 2, maxLength: 255, example: 'calendar-sync'), new OA\Property(property: 'description', type: 'string', example: 'Synchronisation ICS bidirectionnelle'), new OA\Property(property: 'private', type: 'boolean', example: false), new OA\Property(property: 'enabled', type: 'boolean', example: true)])), responses: [new OA\Response(response: 201, description: 'Créé'), new OA\Response(response: 400, description: 'Payload invalide')])]
#[OA\Patch(path: '/v1/plugin/{id}', operationId: 'plugin_patch', summary: 'Modifier partiellement un plugin', tags: ['Plugin Management'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'description', type: 'string', example: 'Sync calendriers externes'), new OA\Property(property: 'enabled', type: 'boolean', example: false)])), responses: [new OA\Response(response: 200, description: 'Mis à jour')])]
#[OA\Delete(path: '/v1/plugin/{id}', operationId: 'plugin_delete', summary: 'Supprimer un plugin', tags: ['Plugin Management'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], responses: [new OA\Response(response: 200, description: 'Supprimé')])]
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
