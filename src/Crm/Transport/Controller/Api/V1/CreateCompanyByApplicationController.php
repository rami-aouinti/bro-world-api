<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Company;
use App\General\Application\Message\EntityCreated;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class CreateCompanyByApplicationController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws JsonException
     * @throws ExceptionInterface
     */
    #[Route('/v1/crm/applications/{applicationSlug}/companies', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(
        summary: 'Create a company scoped to the CRM application',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Acme'),
                    new OA\Property(property: 'industry', type: 'string', nullable: true, example: 'SaaS'),
                    new OA\Property(property: 'website', type: 'string', nullable: true, example: 'https://acme.example'),
                    new OA\Property(property: 'contactEmail', type: 'string', nullable: true, example: 'contact@acme.example'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+33102030405'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Company created in the scoped CRM application.'),
        ],
    )]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $company = new Company()
            ->setCrm($crm)
            ->setName((string)($payload['name'] ?? ''))
            ->setIndustry(isset($payload['industry']) ? (string)$payload['industry'] : null)
            ->setWebsite(isset($payload['website']) ? (string)$payload['website'] : null)
            ->setContactEmail(isset($payload['contactEmail']) ? (string)$payload['contactEmail'] : null)
            ->setPhone(isset($payload['phone']) ? (string)$payload['phone'] : null);

        $this->entityManager->persist($company);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_company', $company->getId(), context: [
            'applicationSlug' => $applicationSlug,
        ]));

        return new JsonResponse([
            'id' => $company->getId(),
            'crmId' => $crm->getId(),
            'applicationSlug' => $applicationSlug,
        ], JsonResponse::HTTP_CREATED);
    }
}
