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
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @package App\User
 */
#[AsController]
#[OA\Tag(name: 'Profile')]
class ConfigurationController
{
    public function __construct(
        private readonly ConfigurationResource $configurationResource,
    ) {
    }

    #[Route(
        path: '/v1/profile/configuration/{configurationKey}',
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    #[OA\Response(
        response: 200,
        description: 'Logged in user configuration by key',
        content: new JsonContent(
            properties: [
                new Property(property: 'configurationKey', type: 'string', example: 'user.notifications.preferences'),
                new Property(property: 'configurationValue', type: 'array', items: new OA\Items(type: 'object')),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 404, description: 'Configuration not found')]
    public function __invoke(User $loggedInUser, string $configurationKey): JsonResponse
    {
        $configuration = $this->findUserConfiguration($loggedInUser, $configurationKey);

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
