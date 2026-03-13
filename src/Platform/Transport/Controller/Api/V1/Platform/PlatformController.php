<?php

declare(strict_types=1);

namespace App\Platform\Transport\Controller\Api\V1\Platform;

use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\ResponseHandler;
use App\General\Transport\Rest\Traits\Actions;
use App\Platform\Application\DTO\Platform\PlatformCreate;
use App\Platform\Application\DTO\Platform\PlatformPatch;
use App\Platform\Application\DTO\Platform\PlatformUpdate;
use App\Platform\Application\Resource\PlatformResource;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @package App\Platform
 *
 * @method PlatformResource getResource()
 * @method ResponseHandler getResponseHandler()
 */
#[AsController]
#[Route(
    path: '/v1/platform',
)]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Platform Management')]
#[OA\Get(path: '/v1/platform', operationId: 'platform_list', summary: 'Lister les plateformes', tags: ['Platform Management'], parameters: [new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)), new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 20))], responses: [new OA\Response(response: 200, description: 'Liste des plateformes'), new OA\Response(response: 403, description: 'Accès refusé')])]
#[OA\Post(path: '/v1/platform', operationId: 'platform_create', summary: 'Créer une plateforme', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name', 'description', 'private', 'enabled'], properties: [new OA\Property(property: 'name', type: 'string', minLength: 2, maxLength: 255, example: 'bro-world'), new OA\Property(property: 'description', type: 'string', example: 'Plateforme principale B2B'), new OA\Property(property: 'private', type: 'boolean', example: false), new OA\Property(property: 'enabled', type: 'boolean', example: true)])), tags: ['Platform Management'], responses: [new OA\Response(response: 201, description: 'Créée'), new OA\Response(response: 400, description: 'Payload invalide')])]
#[OA\Patch(path: '/v1/platform/{id}', operationId: 'platform_patch', summary: 'Modifier partiellement une plateforme', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'name', type: 'string', minLength: 2, maxLength: 255, example: 'bro-world-pro'), new OA\Property(property: 'enabled', type: 'boolean', example: false)])), tags: ['Platform Management'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], responses: [new OA\Response(response: 200, description: 'Mise à jour'), new OA\Response(response: 400, description: 'UUID/payload invalide')])]
#[OA\Delete(path: '/v1/platform/{id}', operationId: 'platform_delete', summary: 'Supprimer une plateforme', tags: ['Platform Management'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], responses: [new OA\Response(response: 200, description: 'Supprimée'), new OA\Response(response: 403, description: 'Accès refusé')])]
class PlatformController extends Controller
{
    use Actions\Admin\CountAction;
    use Actions\Admin\FindAction;
    use Actions\Admin\FindOneAction;
    use Actions\Admin\IdsAction;
    use Actions\Root\CreateAction;
    use Actions\Root\DeleteAction;
    use Actions\Root\PatchAction;
    use Actions\Root\UpdateAction;

    /**
     * @var array<string, string>
     */
    protected static array $dtoClasses = [
        Controller::METHOD_CREATE => PlatformCreate::class,
        Controller::METHOD_UPDATE => PlatformUpdate::class,
        Controller::METHOD_PATCH => PlatformPatch::class,
    ];

    public function __construct(
        PlatformResource $resource,
    ) {
        parent::__construct($resource);
    }
}
