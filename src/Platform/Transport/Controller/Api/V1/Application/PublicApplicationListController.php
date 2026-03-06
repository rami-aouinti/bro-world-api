<?php

declare(strict_types=1);

namespace App\Platform\Transport\Controller\Api\V1\Application;

use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformStatus;
use App\User\Application\Security\UserTypeIdentification;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * @package App\Platform
 */
#[AsController]
#[OA\Tag(name: 'Application')]
class PublicApplicationListController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserTypeIdentification $userTypeIdentification,
    ) {
    }

    #[Route(
        path: '/v1/application/public',
        methods: [Request::METHOD_GET],
    )]
    #[OA\Get(
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List active public applications + private applications of current user when authenticated.',
                content: new JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new Property(property: 'id', type: 'string'),
                            new Property(property: 'title', type: 'string'),
                            new Property(property: 'status', type: 'string'),
                            new Property(property: 'private', type: 'boolean'),
                            new Property(property: 'platformId', type: 'string'),
                            new Property(property: 'platformName', type: 'string'),
                            new Property(property: 'ownerId', type: 'string', nullable: true),
                        ],
                        type: 'object',
                    ),
                ),
            ),
        ],
    )]
    /**
     * @throws Throwable
     */
    public function __invoke(): JsonResponse
    {
        $queryBuilder = $this->entityManager
            ->getRepository(Application::class)
            ->createQueryBuilder('application')
            ->leftJoin('application.platform', 'platform')
            ->addSelect('platform');

        $queryBuilder
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq('application.status', ':activeStatus'),
                    $queryBuilder->expr()->eq('application.private', ':publicApplication'),
                )
            )
            ->setParameter('activeStatus', PlatformStatus::ACTIVE)
            ->setParameter('publicApplication', false);

        $loggedInUser = $this->userTypeIdentification->getUser();

        if ($loggedInUser !== null) {
            $queryBuilder
                ->orWhere('application.user = :loggedInUser')
                ->setParameter('loggedInUser', $loggedInUser);
        }

        /** @var array<int, Application> $applications */
        $applications = $queryBuilder
            ->orderBy('application.title', 'ASC')
            ->addOrderBy('application.id', 'ASC')
            ->getQuery()
            ->getResult();

        $output = [];

        foreach ($applications as $application) {
            $output[] = [
                'id' => $application->getId(),
                'title' => $application->getTitle(),
                'status' => $application->getStatus()->value,
                'private' => $application->isPrivate(),
                'platformId' => $application->getPlatform()?->getId(),
                'platformName' => $application->getPlatform()?->getName(),
                'ownerId' => $application->getUser()?->getId(),
            ];
        }

        return new JsonResponse($output);
    }
}
