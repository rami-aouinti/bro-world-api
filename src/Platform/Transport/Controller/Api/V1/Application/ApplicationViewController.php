<?php

declare(strict_types=1);

namespace App\Platform\Transport\Controller\Api\V1\Application;

use App\Configuration\Domain\Entity\Configuration;
use App\General\Application\Service\ApplicationScopeResolver;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Entity\ApplicationPlugin;
use App\Platform\Infrastructure\Repository\ApplicationRepository;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Application')]
readonly class ApplicationViewController
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private ApplicationScopeResolver $applicationScopeResolver,
    ) {
    }

    #[Route(path: '/v1/application/private/view', methods: [Request::METHOD_GET])]
    public function __invoke(Request $request, ?User $loggedInUser = null): JsonResponse
    {
        $applicationSlug = $this->applicationScopeResolver->resolveFromRequest($request);
        $application = $this->applicationRepository->findOneBy([
            'slug' => $applicationSlug,
        ]);
        if (!$application instanceof Application) {
            return new JsonResponse([
                'message' => 'Application not found.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($application->isPrivate() && ($loggedInUser?->getId() !== $application->getUser()?->getId())) {
            return new JsonResponse([
                'message' => 'Application not found.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $pluginKeys = [];
        foreach ($application->getApplicationPlugins() as $applicationPlugin) {
            $pluginKey = $applicationPlugin->getPlugin()?->getPluginKeyValue();
            if ($pluginKey !== null) {
                $pluginKeys[$pluginKey] = true;
            }
        }

        return new JsonResponse([
            'id' => $application->getId(),
            'title' => $application->getTitle(),
            'slug' => $application->getSlug(),
            'description' => $application->getDescription(),
            'photo' => $application->getPhoto(),
            'status' => $application->getStatus()->value,
            'private' => $application->isPrivate(),
            'platformId' => $application->getPlatform()?->getId(),
            'platformName' => $application->getPlatform()?->getName(),
            'platformKey' => $application->getPlatform()?->getPlatformKeyValue(),
            'pluginKeys' => array_keys($pluginKeys),
            'plugins' => array_map(static fn (ApplicationPlugin $applicationPlugin) => [
                'id' => $applicationPlugin->getId(),
                'name' => $applicationPlugin->getPlugin()?->getName() ?? '',
                'configurations' => array_map(static fn (Configuration $configuration) => [
                    'id' => $configuration->getId(),
                    'name' => $configuration->getConfigurationKey() ?? '',
                    'configuration' => $configuration->getConfigurationValue(),
                ], $applicationPlugin->getConfigurations()->toArray()),
            ], $application->getApplicationPlugins()->toArray()),
            'author' => [
                'id' => $application->getUser()?->getId(),
                'firstName' => $application->getUser()?->getFirstName() ?? '',
                'lastName' => $application->getUser()?->getLastName() ?? '',
                'photo' => $application->getUser()?->getPhoto() ?? '',
            ],
            'createdAt' => $application->getCreatedAt()?->format(DATE_ATOM),
            'isOwner' => $loggedInUser !== null && $application->getUser()?->getId() === $loggedInUser->getId(),
        ]);
    }
}
