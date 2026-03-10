<?php

declare(strict_types=1);

namespace App\Configuration\Transport\Controller\Api\V1\Configuration;

use App\Configuration\Application\DTO\Configuration\ConfigurationCreate;
use App\Configuration\Application\DTO\Configuration\ConfigurationPatch;
use App\Configuration\Application\DTO\Configuration\ConfigurationUpdate;
use App\Configuration\Application\Message\CreateConfigurationCommand;
use App\Configuration\Application\Message\DeleteConfigurationCommand;
use App\Configuration\Application\Message\PatchConfigurationCommand;
use App\Configuration\Application\Resource\ConfigurationResource;
use App\General\Application\DTO\Interfaces\RestDtoInterface;
use App\General\Domain\Service\Interfaces\MessageServiceInterface;
use App\General\Transport\Http\ValidationErrorFactory;
use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\ResponseHandler;
use App\General\Transport\Rest\Traits\Actions;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * @package App\Configuration
 *
 * @method ConfigurationResource getResource()
 * @method ResponseHandler getResponseHandler()
 */
#[AsController]
#[Route(path: '/v1/configuration')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Configuration Management')]
#[OA\Get(
    path: '/v1/configuration',
    operationId: 'configuration_list',
    summary: 'Lister les configurations',
    tags: ['Configuration Management'],
    parameters: [
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)),
        new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 20)),
    ],
    responses: [new OA\Response(response: 200, description: 'Liste de configurations'), new OA\Response(response: 403, description: 'Accès refusé')],
)]
#[OA\Post(
    path: '/v1/configuration',
    operationId: 'configuration_create',
    summary: 'Créer une configuration',
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['configurationKey', 'configurationValue', 'scope', 'private'], properties: [
        new OA\Property(property: 'configurationKey', type: 'string', minLength: 2, maxLength: 255, example: 'mail.smtp.timeout'),
        new OA\Property(property: 'configurationValue', type: 'object', example: [
            'seconds' => 30,
            'retry' => 3,
        ]),
        new OA\Property(property: 'scope', type: 'string', enum: ['system', 'user', 'platform', 'plugin', 'public'], example: 'system'),
        new OA\Property(property: 'private', type: 'boolean', example: false),
    ])),
    tags: ['Configuration Management'],
    responses: [new OA\Response(response: 202, description: 'Commande acceptée'), new OA\Response(response: 400, description: 'Payload invalide'), new OA\Response(response: 403, description: 'Accès refusé'), new OA\Response(response: 500, description: 'Erreur interne')],
)]
#[OA\Patch(
    path: '/v1/configuration/{id}',
    operationId: 'configuration_patch',
    summary: 'Modifier partiellement une configuration',
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
        new OA\Property(property: 'configurationValue', type: 'object', example: [
            'seconds' => 45,
        ]),
        new OA\Property(property: 'scope', type: 'string', enum: ['system', 'user', 'platform', 'plugin', 'public'], example: 'platform'),
        new OA\Property(property: 'private', type: 'boolean', example: true),
    ])),
    tags: ['Configuration Management'],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))],
    responses: [new OA\Response(response: 202, description: 'Commande acceptée'), new OA\Response(response: 400, description: 'UUID ou payload invalide'), new OA\Response(response: 422, description: 'Règles métier invalides')],
)]
#[OA\Delete(
    path: '/v1/configuration/{id}',
    operationId: 'configuration_delete',
    summary: 'Supprimer une configuration',
    tags: ['Configuration Management'],
    parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))],
    responses: [new OA\Response(response: 202, description: 'Suppression asynchrone acceptée'), new OA\Response(response: 400, description: 'UUID invalide'), new OA\Response(response: 403, description: 'Accès refusé')],
)]
class ConfigurationController extends Controller
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
        Controller::METHOD_CREATE => ConfigurationCreate::class,
        Controller::METHOD_UPDATE => ConfigurationUpdate::class,
        Controller::METHOD_PATCH => ConfigurationPatch::class,
    ];

    public function __construct(
        ConfigurationResource $resource,
        private readonly MessageServiceInterface $messageService,
    ) {
        parent::__construct($resource);
    }

    public function createMethod(Request $request, RestDtoInterface $restDto, ?array $allowedHttpMethods = null): Response
    {
        if (!$restDto instanceof ConfigurationCreate) {
            throw ValidationErrorFactory::badRequest('Invalid payload for configuration creation.');
        }

        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageService->sendMessage(new CreateConfigurationCommand($operationId, $restDto));

        return new JsonResponse([
            'operationId' => $operationId,
        ], Response::HTTP_ACCEPTED);
    }

    public function patchMethod(
        Request $request,
        RestDtoInterface $restDto,
        string $id,
        ?array $allowedHttpMethods = null,
    ): Response {
        if (!Uuid::isValid($id)) {
            throw ValidationErrorFactory::badRequest('Field "id" must be a valid UUID.');
        }

        if (!$restDto instanceof ConfigurationPatch) {
            throw ValidationErrorFactory::badRequest('Invalid payload for configuration patch.');
        }

        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageService->sendMessage(new PatchConfigurationCommand($operationId, $id, $restDto));

        return new JsonResponse([
            'operationId' => $operationId,
            'id' => $id,
        ], Response::HTTP_ACCEPTED);
    }

    public function deleteMethod(Request $request, string $id, ?array $allowedHttpMethods = null): Response
    {
        if (!Uuid::isValid($id)) {
            throw ValidationErrorFactory::badRequest('Field "id" must be a valid UUID.');
        }

        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageService->sendMessage(new DeleteConfigurationCommand($operationId, $id));

        return new JsonResponse([
            'operationId' => $operationId,
            'id' => $id,
        ], Response::HTTP_ACCEPTED);
    }
}
