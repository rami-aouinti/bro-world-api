<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Company;

use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\Traits\Actions;
use App\Recruit\Application\DTO\Company\CompanyCreate;
use App\Recruit\Application\DTO\Company\CompanyPatch;
use App\Recruit\Application\DTO\Company\CompanyUpdate;
use App\Recruit\Application\Resource\CompanyResource;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[Route(path: '/v1/recruit/company')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Recruit Company Management')]
#[OA\Get(path: '/v1/recruit/company', operationId: 'recruit_company_list', summary: 'Lister les entreprises', tags: ['Recruit Company Management'], parameters: [new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)), new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 20))], responses: [new OA\Response(response: 200, description: 'Liste des entreprises')])]
#[OA\Post(path: '/v1/recruit/company', operationId: 'recruit_company_create', summary: 'Créer une entreprise', tags: ['Recruit Company Management'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name', 'sector', 'size'], properties: [new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Acme Hiring'), new OA\Property(property: 'logo', type: 'string', nullable: true, example: 'https://cdn.example.com/acme.png'), new OA\Property(property: 'sector', type: 'string', maxLength: 100, example: 'SaaS RH'), new OA\Property(property: 'size', type: 'string', maxLength: 50, example: '51-200')])), responses: [new OA\Response(response: 201, description: 'Entreprise créée')])]
#[OA\Patch(path: '/v1/recruit/company/{id}', operationId: 'recruit_company_patch', summary: 'Modifier une entreprise', tags: ['Recruit Company Management'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'name', type: 'string', example: 'Acme Hiring Europe'), new OA\Property(property: 'size', type: 'string', example: '201-500')])), responses: [new OA\Response(response: 200, description: 'Entreprise mise à jour')])]
#[OA\Delete(path: '/v1/recruit/company/{id}', operationId: 'recruit_company_delete', summary: 'Supprimer une entreprise', tags: ['Recruit Company Management'], parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], responses: [new OA\Response(response: 200, description: 'Entreprise supprimée')])]
class CompanyController extends Controller
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
        Controller::METHOD_CREATE => CompanyCreate::class,
        Controller::METHOD_UPDATE => CompanyUpdate::class,
        Controller::METHOD_PATCH => CompanyPatch::class,
    ];

    public function __construct(CompanyResource $resource)
    {
        parent::__construct($resource);
    }
}
