<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\Profile;

use App\Configuration\Application\Resource\ConfigurationResource;
use App\Configuration\Domain\Entity\Configuration;
use App\Configuration\Domain\Enum\ConfigurationScope;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_array;
use function is_string;

/**
 * @package App\User
 */
#[AsController]
#[OA\Tag(name: 'Profile')]
class ConfigurationCreateController
{
    public function __construct(
        private readonly ConfigurationResource $configurationResource,
    ) {
    }

    #[Route(
        path: '/v1/profile/configuration',
        methods: [Request::METHOD_POST],
    )]
    #[OA\Post(summary: 'POST /v1/profile/configuration', tags: ['Profile'], parameters: [], responses: [new OA\Response(response: 201, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    #[OA\RequestBody(
        required: true,
        content: new JsonContent(
            type: 'object',
            example: [
                'configurationKey' => 'user.dashboard.filters',
                'configurationValue' => [
                    'category' => 'sales',
                    'showArchived' => false,
                ],
            ],
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Created logged in user configuration',
        content: new JsonContent(
            properties: [
                new Property(property: 'configurationKey', type: 'string', example: 'user.dashboard.filters'),
                new Property(property: 'configurationValue', type: 'array', items: new OA\Items(type: 'object')),
                new Property(property: 'scope', type: 'string', example: 'user'),
            ],
            type: 'object',
        ),
    )]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $configurationKey = $payload['configurationKey'] ?? null;
        $configurationValue = $payload['configurationValue'] ?? null;

        if (!is_string($configurationKey) || $configurationKey === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "configurationKey" must be a non-empty string.');
        }

        if (!is_array($configurationValue)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "configurationValue" must be an array.');
        }

        $existing = $this->configurationResource->findOneBy([
            'configurationKey' => $configurationKey,
            'user' => $loggedInUser,
        ]);

        if ($existing instanceof Configuration) {
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Configuration key already exists for current user.');
        }

        $configuration = new Configuration();
        $configuration
            ->setUser($loggedInUser)
            ->setConfigurationKey($configurationKey)
            ->setConfigurationValue($configurationValue)
            ->setScope(ConfigurationScope::USER)
            ->setPrivate(true);

        $this->configurationResource->save($configuration);

        return new JsonResponse([
            'configurationKey' => $configuration->getConfigurationKey(),
            'configurationValue' => $configuration->getConfigurationValue(),
            'scope' => $configuration->getScopeValue(),
        ], JsonResponse::HTTP_CREATED);
    }
}
