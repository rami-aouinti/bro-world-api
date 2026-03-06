<?php

declare(strict_types=1);

namespace App\Platform\Transport\Controller\Api\V1\Application;

use App\Platform\Domain\Entity\Application;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

/**
 * @package App\Platform
 */
#[AsController]
#[OA\Tag(name: 'Application')]
class PrivateApplicationListController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(
        path: '/v1/application/private',
        methods: [Request::METHOD_GET],
    )]
    #[OA\Get(
        responses: [
            new OA\Response(
                response: 200,
                description: 'List all public applications and authenticated user applications (public or private).',
                content: new JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new Property(property: 'id', type: 'string'),
                            new Property(property: 'title', type: 'string'),
                            new Property(property: 'slug', type: 'string'),
                            new Property(property: 'description', type: 'string'),
                            new Property(property: 'photo', type: 'string'),
                            new Property(property: 'status', type: 'string'),
                            new Property(property: 'private', type: 'boolean'),
                            new Property(property: 'platformId', type: 'string'),
                            new Property(property: 'platformName', type: 'string'),
                            new Property(
                                property: 'author',
                                properties: [
                                    new Property(property: 'id', type: 'string', nullable: true),
                                    new Property(property: 'firstName', type: 'string'),
                                    new Property(property: 'lastName', type: 'string'),
                                    new Property(property: 'photo', type: 'string'),
                                ],
                                type: 'object',
                            ),
                            new Property(property: 'createdAt', type: 'string', nullable: true),
                            new Property(property: 'isOwner', type: 'boolean'),
                        ],
                        type: 'object',
                    ),
                ),
            ),
        ],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    /**
     * @throws Throwable
     */
    public function __invoke(User $loggedInUser): JsonResponse
    {
        /** @var array<int, Application> $applications */
        $applications = $this->entityManager
            ->getRepository(Application::class)
            ->createQueryBuilder('application')
            ->leftJoin('application.platform', 'platform')
            ->leftJoin('application.user', 'user')
            ->addSelect('platform')
            ->addSelect('user')
            ->where('application.private = :publicApplication')
            ->orWhere('application.user = :loggedInUser')
            ->setParameter('publicApplication', false)
            ->setParameter('loggedInUser', $loggedInUser)
            ->orderBy('application.title', 'ASC')
            ->addOrderBy('application.id', 'ASC')
            ->getQuery()
            ->getResult();

        $output = [];

        foreach ($applications as $application) {
            $output[] = [
                'id' => $application->getId(),
                'title' => $application->getTitle(),
                'slug' => $application->getSlug(),
                'description' => $application->getDescription(),
                'photo' => $application->getPhoto(),
                'status' => $application->getStatus()->value,
                'private' => $application->isPrivate(),
                'platformId' => $application->getPlatform()?->getId(),
                'platformName' => $application->getPlatform()?->getName(),
                'author' => [
                    'id' => $application->getUser()?->getId(),
                    'firstName' => $application->getUser()?->getFirstName() ?? '',
                    'lastName' => $application->getUser()?->getLastName() ?? '',
                    'photo' => $application->getUser()?->getPhoto() ?? '',
                ],
                'createdAt' => $application->getCreatedAt()?->format(DATE_ATOM),
                'isOwner' => $application->getUser()?->getId() === $loggedInUser?->getId(),
            ];
        }

        return new JsonResponse($output);
    }
}
