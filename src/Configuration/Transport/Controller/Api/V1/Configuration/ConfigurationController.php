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

        $operationId = Uuid::v4()->toRfc4122();
        $this->messageService->sendMessage(new CreateConfigurationCommand($operationId, $restDto));

        return new JsonResponse(['operationId' => $operationId], Response::HTTP_ACCEPTED);
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

        $operationId = Uuid::v4()->toRfc4122();
        $this->messageService->sendMessage(new PatchConfigurationCommand($operationId, $id, $restDto));

        return new JsonResponse(['operationId' => $operationId, 'id' => $id], Response::HTTP_ACCEPTED);
    }

    public function deleteMethod(Request $request, string $id, ?array $allowedHttpMethods = null): Response
    {
        if (!Uuid::isValid($id)) {
            throw ValidationErrorFactory::badRequest('Field "id" must be a valid UUID.');
        }

        $operationId = Uuid::v4()->toRfc4122();
        $this->messageService->sendMessage(new DeleteConfigurationCommand($operationId, $id));

        return new JsonResponse(['operationId' => $operationId, 'id' => $id], Response::HTTP_ACCEPTED);
    }
}
