<?php

declare(strict_types=1);

namespace App\Platform\Transport\Controller\Api\V1\Application;

use App\Platform\Domain\Entity\Application;
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
                description: 'List public applications.',
                content: new JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new Property(property: 'id', type: 'string'),
                            new Property(property: 'title', type: 'string'),
                            new Property(property: 'description', type: 'string'),
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
        /** @var array<int, Application> $applications */
        $applications = $this->entityManager
            ->getRepository(Application::class)
            ->createQueryBuilder('application')
            ->leftJoin('application.platform', 'platform')
            ->leftJoin('application.user', 'user')
            ->addSelect('platform')
            ->addSelect('user')
            ->where('application.private = :publicApplication')
            ->setParameter('publicApplication', false)
            ->orderBy('application.title', 'ASC')
            ->addOrderBy('application.id', 'ASC')
            ->getQuery()
            ->getResult();

        $output = [];

        foreach ($applications as $application) {
            $output[] = [
                'id' => $application->getId(),
                'title' => $application->getTitle(),
                'description' => $application->getDescription(),
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
            ];
        }

        return new JsonResponse($output);
    }
}
