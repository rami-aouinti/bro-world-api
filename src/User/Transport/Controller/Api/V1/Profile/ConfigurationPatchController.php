<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\Profile;

use App\Configuration\Application\Resource\ConfigurationResource;
use App\Configuration\Domain\Entity\Configuration;
use App\Configuration\Domain\Enum\ConfigurationScope;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\JsonContent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_array;

/**
 * @package App\User
 */
#[AsController]
#[OA\Tag(name: 'Profile')]
class ConfigurationPatchController
{
    public function __construct(
        private readonly ConfigurationResource $configurationResource,
    ) {
    }

    #[Route(
        path: '/v1/profile/configuration/{configurationKey}',
        methods: [Request::METHOD_PATCH],
    )]
    #[OA\Patch(summary: 'PATCH /v1/profile/configuration/{configurationKey}', tags: ['Profile'], parameters: [new OA\Parameter(name: 'configurationKey', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    #[OA\RequestBody(
        required: true,
        content: new JsonContent(
            type: 'object',
            example: [
                'configurationValue' => [
                    [
                        'switchState' => false,
                        'text' => 'Email me when someone follows me',
                    ],
                ],
            ],
        ),
    )]
    public function __invoke(Request $request, User $loggedInUser, string $configurationKey): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $configurationValue = $payload['configurationValue'] ?? null;

        if (!is_array($configurationValue)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "configurationValue" must be an array.');
        }

        $configuration = $this->findUserConfiguration($loggedInUser, $configurationKey);
        $configuration->setConfigurationValue($configurationValue);
        $this->configurationResource->save($configuration);

        return new JsonResponse([
            'configurationKey' => $configurationKey,
            'configurationValue' => $configuration->getConfigurationValue(),
        ]);
    }

    private function findUserConfiguration(User $loggedInUser, string $configurationKey): Configuration
    {
        $configuration = $this->configurationResource->findOneBy(
            criteria: [
                'configurationKey' => $configurationKey,
                'user' => $loggedInUser,
                'scope' => ConfigurationScope::USER,
                'private' => true,
            ],
            throwExceptionIfNotFound: true,
        );

        return $configuration instanceof Configuration
            ? $configuration
            : throw new \RuntimeException('User configuration not found.');
    }
}
