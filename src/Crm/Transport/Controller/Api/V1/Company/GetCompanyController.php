<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Company;

use App\Crm\Domain\Entity\Company;
use App\Crm\Domain\Entity\Project;
use App\General\Application\Service\CacheKeyConventionService;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

use function method_exists;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class GetCompanyController
{
    public function __construct(
        private CacheInterface $cache,
        private CacheKeyConventionService $cacheKeyConventionService,
    ) {
    }

    #[Route('/v1/crm/companies/{company}', methods: [Request::METHOD_GET])]
        #[OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Get(
        description: 'Exécute l action metier Get Company dans le perimetre de l application CRM.',
        summary: 'Get Company',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Opération exécutée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $applicationSlug, Company $company): JsonResponse
    {
        $cacheKey = $this->cacheKeyConventionService->buildCrmCompanyDetailKey($applicationSlug, $company->getId());

        $payload = $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug, $company): ?array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag([
                    $this->cacheKeyConventionService->crmCompanyListByApplicationTag($applicationSlug),
                    $this->cacheKeyConventionService->crmCompanyDetailTag($applicationSlug, $company->getId()),
                ]);
            }

            return [
                'id' => $company->getId(),
                'name' => $company->getName(),
                'industry' => $company->getIndustry(),
                'website' => $company->getWebsite(),
                'contactEmail' => $company->getContactEmail(),
                'phone' => $company->getPhone(),
                'projects' => array_map(
                    static fn (Project $project) =>
                    [
                        'id' => $project->getId(),
                        'name' => $project->getName(),
                    ],
                    $company->getProjects()->toArray()
                ),
            ];
        });

        if ($payload === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Company not found for this CRM scope.');
        }

        return new JsonResponse($payload);
    }
}
