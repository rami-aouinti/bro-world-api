<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Sprint;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmEntityBlogProvisioningService;
use App\Crm\Domain\Entity\Sprint;
use App\Crm\Domain\Enum\SprintStatus;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Transport\Request\CreateSprintRequest;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\General\Application\Message\EntityCreated;
use App\Role\Domain\Enum\Role;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class CreateSprintController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmEntityBlogProvisioningService $crmEntityBlogProvisioningService,
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/sprints', methods: [Request::METHOD_POST])]
        #[OA\Post(
        summary: 'Create Sprint',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'projectId'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Sprint Q1 - Pipeline'),
                    new OA\Property(property: 'goal', type: 'string', maxLength: 5000, example: 'Automatiser la qualification des leads.', nullable: true),
                    new OA\Property(property: 'status', type: 'string', enum: ['planned', 'active', 'closed'], example: 'planned', nullable: true),
                    new OA\Property(property: 'startDate', type: 'string', format: 'date-time', example: '2026-02-01T08:00:00+00:00', nullable: true),
                    new OA\Property(property: 'endDate', type: 'string', format: 'date-time', example: '2026-02-14T18:00:00+00:00', nullable: true),
                    new OA\Property(property: 'projectId', type: 'string', format: 'uuid', example: 'ebf77366-d60c-4ac4-b204-9f91a7f7ee12'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Sprint created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '220670e1-4bc3-40da-92bb-89d5dca347a8'),
                        new OA\Property(property: 'blogId', type: 'string', format: 'uuid', nullable: true, example: '1d2f3a4b-5c6d-7e8f-9012-3456789abcde'),
                    ],
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid JSON payload or invalid date format.',
                content: new OA\JsonContent(
                    examples: [
                        'invalidJson' => new OA\Examples(example: 'invalidJson', summary: 'JSON invalide', value: [
                            'message' => 'Invalid JSON payload.',
                            'errors' => [],
                        ]),
                        'invalidDate' => new OA\Examples(example: 'invalidDate', summary: 'Date invalide', value: [
                            'message' => 'Invalid date format for "endDate".',
                            'errors' => [],
                        ]),
                    ],
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Referenced resource not found in CRM scope.',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'Unknown "projectId" in this CRM scope.',
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

        try {
            $payload = json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        $input = CreateSprintRequest::fromArray($payload);
        $violations = $this->validator->validate($input);
        if ($violations->count() > 0) {
            return $this->errorResponseFactory->validationFailed($violations);
        }

        $startDate = $this->parseDate($input->startDate, 'startDate');
        if ($startDate instanceof JsonResponse) {
            return $startDate;
        }

        $endDate = $this->parseDate($input->endDate, 'endDate');
        if ($endDate instanceof JsonResponse) {
            return $endDate;
        }

        $sprint = new Sprint();
        $sprint->setName((string)$input->name)
            ->setGoal($input->goal)
            ->setStatus(SprintStatus::tryFrom((string)$input->status) ?? SprintStatus::PLANNED)
            ->setStartDate($startDate)
            ->setEndDate($endDate);

        if (is_string($input->projectId)) {
            $project = $this->projectRepository->findOneScopedById($input->projectId, $crm->getId());
            if ($project === null) {
                return $this->errorResponseFactory->notFoundReference('projectId');
            }

            $sprint->setProject($project);
        }

        $this->entityManager->persist($sprint);
        $this->crmEntityBlogProvisioningService->provision($sprint);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_sprint', $sprint->getId(), context: [
            'applicationSlug' => $applicationSlug,
            'crmId' => $crm->getId(),
        ]));

        return new JsonResponse([
            'id' => $sprint->getId(),
            'blogId' => $sprint->getBlog()?->getId(),
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
