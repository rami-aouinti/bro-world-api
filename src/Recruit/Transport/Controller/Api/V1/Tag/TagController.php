<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Tag;

use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\Traits\Actions;
use App\Recruit\Application\DTO\Tag\TagCreate;
use App\Recruit\Application\DTO\Tag\TagPatch;
use App\Recruit\Application\DTO\Tag\TagUpdate;
use App\Recruit\Application\Resource\TagResource;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route(path: '/v1/recruit/tag')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Recruit Tag Management')]
#[OA\Get(path: '/v1/recruit/tag', operationId: 'recruit_tag_list', summary: 'Lister les tags de recrutement', tags: ['Recruit Tag Management'], parameters: [new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)), new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 20))], responses: [new OA\Response(response: 200, description: 'Liste des tags')])]
#[OA\Post(path: '/v1/recruit/tag', operationId: 'recruit_tag_create', summary: 'Créer un tag recrutement', tags: ['Recruit Tag Management'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['label'], properties: [new OA\Property(property: 'label', type: 'string', minLength: 1, maxLength: 80, example: 'Symfony')])), responses: [new OA\Response(response: 201, description: 'Tag créé'), new OA\Response(response: 400, description: 'Payload invalide')])]
#[OA\Patch(path: '/v1/recruit/tag/{id}', operationId: 'recruit_tag_patch', summary: 'Modifier un tag recrutement', tags: ['Recruit Tag Management'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'label', type: 'string', minLength: 1, maxLength: 80, example: 'PHP 8.3')])), responses: [new OA\Response(response: 200, description: 'Tag mis à jour')])]
#[OA\Delete(path: '/v1/recruit/tag/{id}', operationId: 'recruit_tag_delete', summary: 'Supprimer un tag recrutement', tags: ['Recruit Tag Management'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], responses: [new OA\Response(response: 200, description: 'Tag supprimé')])]
class TagController extends Controller
{
    use Actions\Root\CountAction;
    use Actions\Root\FindAction;
    use Actions\Root\FindOneAction;
    use Actions\Root\IdsAction;
    use Actions\Root\CreateAction;
    use Actions\Root\DeleteAction;
    use Actions\Root\PatchAction;
    use Actions\Root\UpdateAction;

    protected static array $dtoClasses = [
        Controller::METHOD_CREATE => TagCreate::class,
        Controller::METHOD_UPDATE => TagUpdate::class,
        Controller::METHOD_PATCH => TagPatch::class,
    ];

    public function __construct(TagResource $resource)
    {
        parent::__construct($resource);
    }
}
