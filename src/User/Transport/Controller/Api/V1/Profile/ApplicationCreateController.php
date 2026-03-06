<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\Profile;

use App\Configuration\Domain\Entity\Configuration;
use App\Configuration\Domain\Enum\ConfigurationScope;
use App\Platform\Application\Resource\PlatformResource;
use App\Platform\Application\Resource\PluginResource;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Entity\ApplicationPlugin;
use App\Platform\Domain\Enum\PlatformStatus;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
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
use Throwable;

use function is_array;
use function is_bool;
use function is_string;
use function trim;

#[AsController]
#[OA\Tag(name: 'Profile')]
class ApplicationCreateController
{
    public function __construct(
        private readonly PlatformResource $platformResource,
        private readonly PluginResource $pluginResource,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws Throwable
     */
    #[Route(
        path: '/v1/profile/applications',
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    #[OA\RequestBody(
        required: true,
        content: new JsonContent(
            type: 'object',
            example: [
                'platformId' => '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e70',
                'title' => 'My Ecommerce App',
                'description' => 'Application description',
                'status' => 'active',
                'private' => false,
                'configurations' => [
                    [
                        'configurationKey' => 'app.theme',
                        'configurationValue' => ['name' => 'dark'],
                    ],
                ],
                'plugins' => [
                    [
                        'pluginId' => '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e71',
                        'configurations' => [
                            [
                                'configurationKey' => 'plugin.cache.ttl',
                                'configurationValue' => ['seconds' => 120],
                            ],
                        ],
                    ],
                ],
            ],
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Application created for logged in user',
        content: new JsonContent(
            properties: [
                new Property(property: 'id', type: 'string'),
                new Property(property: 'platformId', type: 'string'),
                new Property(property: 'title', type: 'string'),
                new Property(property: 'description', type: 'string'),
                new Property(property: 'status', type: 'string'),
                new Property(property: 'private', type: 'boolean'),
            ],
            type: 'object',
        ),
    )]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();

        $platformId = $payload['platformId'] ?? null;
        $title = $payload['title'] ?? null;
        $description = $payload['description'] ?? '';
        $status = $payload['status'] ?? PlatformStatus::ACTIVE->value;
        $private = $payload['private'] ?? false;

        if (!is_string($platformId) || $platformId === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "platformId" is required.');
        }

        if (!is_string($title) || $title === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "title" is required.');
        }

        if (!is_string($description)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "description" must be a string.');
        }

        if (!is_string($status)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "status" must be a string.');
        }

        if (!is_bool($private)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "private" must be a boolean.');
        }

        $platform = $this->platformResource->findOne($platformId, true);

        $application = (new Application())
            ->setUser($loggedInUser)
            ->setPlatform($platform)
            ->setTitle($title)
            ->setDescription(trim($description))
            ->setStatus($status)
            ->setPrivate($private);

        $configurations = $payload['configurations'] ?? [];
        if (!is_array($configurations)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "configurations" must be an array.');
        }

        foreach ($configurations as $configurationData) {
            if (!is_array($configurationData)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Each item in "configurations" must be an object.');
            }

            $configuration = $this->buildConfiguration($configurationData, ConfigurationScope::PLATFORM);
            $configuration->setApplication($application);
            $application->addConfiguration($configuration);
        }

        $plugins = $payload['plugins'] ?? [];
        if (!is_array($plugins)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "plugins" must be an array.');
        }

        foreach ($plugins as $pluginData) {
            if (!is_array($pluginData)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Each item in "plugins" must be an object.');
            }

            $pluginId = $pluginData['pluginId'] ?? null;
            if (!is_string($pluginId) || $pluginId === '') {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Each plugin item must contain "pluginId".');
            }

            $plugin = $this->pluginResource->findOne($pluginId, true);

            $applicationPlugin = (new ApplicationPlugin())
                ->setApplication($application)
                ->setPlugin($plugin);

            $pluginConfigurations = $pluginData['configurations'] ?? [];
            if (!is_array($pluginConfigurations)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "plugins[].configurations" must be an array.');
            }

            foreach ($pluginConfigurations as $pluginConfigurationData) {
                if (!is_array($pluginConfigurationData)) {
                    throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Each item in "plugins[].configurations" must be an object.');
                }

                $pluginConfiguration = $this->buildConfiguration($pluginConfigurationData, ConfigurationScope::PLUGIN);
                $pluginConfiguration->setApplicationPlugin($applicationPlugin);
                $applicationPlugin->addConfiguration($pluginConfiguration);
            }

            $application->addApplicationPlugin($applicationPlugin);
        }

        $this->entityManager->persist($application);
        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $application->getId(),
            'platformId' => $application->getPlatform()?->getId(),
            'title' => $application->getTitle(),
            'description' => $application->getDescription(),
            'status' => $application->getStatus()->value,
            'private' => $application->isPrivate(),
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * @param array<string, mixed> $configurationData
     */
    private function buildConfiguration(array $configurationData, ConfigurationScope $scope): Configuration
    {
        $configurationKey = $configurationData['configurationKey'] ?? null;
        $configurationValue = $configurationData['configurationValue'] ?? null;

        if (!is_string($configurationKey) || $configurationKey === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "configurationKey" is required for each configuration.');
        }

        if (!is_array($configurationValue)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "configurationValue" must be an array for each configuration.');
        }

        return (new Configuration())
            ->setConfigurationKey($configurationKey)
            ->setConfigurationValue($configurationValue)
            ->setScope($scope)
            ->setPrivate(true);
    }
}
