<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Company;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Company;
use App\Crm\Transport\Request\CreateCompanyRequest;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\General\Application\Message\EntityCreated;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Crm\Transport\Request\CrmRequestHandler;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_OWNER->value)]
final readonly class CreateCompanyByApplicationController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmRequestHandler $crmRequestHandler,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
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
                    new OA\Property(property: 'industry', type: 'string', example: 'SaaS', nullable: true),
                    new OA\Property(property: 'website', type: 'string', example: 'https://acme.example', nullable: true),
                    new OA\Property(property: 'contactEmail', type: 'string', example: 'contact@acme.example', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', example: '+33102030405', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Company created in the scoped CRM application.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '7e2f5f7c-2878-438a-a2ff-27fc2382cedf'),
                        new OA\Property(property: 'crmId', type: 'string', format: 'uuid', example: '0c9b6cba-4ed6-4977-9545-5f8564d5ac7e'),
                        new OA\Property(property: 'applicationSlug', type: 'string', example: 'my-crm-app'),
                    ],
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid JSON payload.',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'Invalid JSON payload.',
                        'errors' => [],
                    ],
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Unknown CRM application scope.',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'Unknown application scope.',
                    ],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed.',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'Validation failed.',
                        'errors' => [
                            [
                                'propertyPath' => 'name',
                                'message' => 'This value should not be blank.',
                                'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                            ],
                        ],
                    ],
                ),
            ),
        ],
    )]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateCompanyRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $company = new Company()
            ->setCrm($crm)
            ->setName((string)$input->name)
            ->setIndustry($input->industry)
            ->setWebsite($input->website)
            ->setContactEmail($input->contactEmail)
            ->setPhone($input->phone);

        $this->entityManager->persist($company);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_company', $company->getId(), context: [
            'applicationSlug' => $applicationSlug,
            'crmId' => $crm->getId(),
        ]));

        return new JsonResponse([
            'id' => $company->getId(),
            'crmId' => $crm->getId(),
            'applicationSlug' => $applicationSlug,
        ], JsonResponse::HTTP_CREATED);
    }
}
