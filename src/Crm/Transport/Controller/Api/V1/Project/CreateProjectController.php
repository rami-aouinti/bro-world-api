<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Project;
use App\Crm\Domain\Enum\ProjectStatus;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Transport\Request\CreateProjectRequest;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\General\Application\Message\EntityCreated;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use App\Crm\Application\Security\CrmPermissions;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(CrmPermissions::EDIT)]
final readonly class CreateProjectController
{
    public function __construct(
        private CompanyRepository $companyRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/v1/crm/applications/{applicationSlug}/projects', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(
        summary: 'POST /v1/crm/applications/{applicationSlug}/projects',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'companyId'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Refonte CRM 2026'),
                    new OA\Property(property: 'code', type: 'string', maxLength: 64, nullable: true, example: 'CRM26'),
                    new OA\Property(property: 'description', type: 'string', maxLength: 5000, nullable: true, example: 'Refonte des workflows commerciaux.'),
                    new OA\Property(property: 'status', type: 'string', enum: ['planned', 'active', 'on_hold', 'completed'], nullable: true, example: 'active'),
                    new OA\Property(property: 'startedAt', type: 'string', format: 'date-time', nullable: true, example: '2026-01-15T09:00:00+00:00'),
                    new OA\Property(property: 'dueAt', type: 'string', format: 'date-time', nullable: true, example: '2026-06-30T18:00:00+00:00'),
                    new OA\Property(property: 'companyId', type: 'string', format: 'uuid', example: '4db7f53d-cf31-4b36-9b9b-78e914c36a39'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Project created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: 'ebf77366-d60c-4ac4-b204-9f91a7f7ee12'),
                    ],
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid JSON payload or invalid date format.',
                content: new OA\JsonContent(
                    examples: [
                        'invalidJson' => new OA\Examples(
                            example: 'invalidJson',
                            summary: 'JSON invalide',
                            value: [
                                'message' => 'Invalid JSON payload.',
                                'errors' => [],
                            ],
                        ),
                        'invalidDate' => new OA\Examples(
                            example: 'invalidDate',
                            summary: 'Date invalide',
                            value: [
                                'message' => 'Invalid date format for "startedAt".',
                                'errors' => [],
                            ],
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Referenced resource not found in CRM scope.',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'Unknown "companyId" in this CRM scope.',
                        'errors' => [],
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
                                'propertyPath' => 'companyId',
                                'message' => 'This is not a valid UUID.',
                                'code' => '51120b12-a2bc-41bf-aa53-cd73daf330d0',
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

        try {
            $payload = json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        $input = CreateProjectRequest::fromArray($payload);
        $violations = $this->validator->validate($input);
        if ($violations->count() > 0) {
            return $this->errorResponseFactory->validationFailed($violations);
        }

        $startedAt = $this->parseDate($input->startedAt, 'startedAt');
        if ($startedAt instanceof JsonResponse) {
            return $startedAt;
        }

        $dueAt = $this->parseDate($input->dueAt, 'dueAt');
        if ($dueAt instanceof JsonResponse) {
            return $dueAt;
        }

        $project = new Project();
        $project->setName((string)$input->name)
            ->setCode($input->code)
            ->setDescription($input->description)
            ->setStatus(ProjectStatus::tryFrom((string)$input->status) ?? ProjectStatus::PLANNED)
            ->setStartedAt($startedAt)
            ->setDueAt($dueAt);

        if (is_string($input->companyId)) {
            $company = $this->companyRepository->findOneScopedById($input->companyId, $crm->getId());
            if ($company === null) {
                return $this->errorResponseFactory->notFoundReference('companyId');
            }

            $project->setCompany($company);
        }

        $this->entityManager->persist($project);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_project', $project->getId(), context: [
            'applicationSlug' => $applicationSlug,
            'crmId' => $crm->getId(),
        ]));

        return new JsonResponse([
            'id' => $project->getId(),
        ], JsonResponse::HTTP_CREATED);
    }

    private function parseDate(?string $value, string $field): DateTimeImmutable|JsonResponse|null
    {
        if ($value === null) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);
        if ($date === false) {
            return $this->errorResponseFactory->invalidDate($field);
        }

        return $date;
    }
}
