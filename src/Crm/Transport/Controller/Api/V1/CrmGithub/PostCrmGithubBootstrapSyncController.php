<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\CrmGithub;

use App\Crm\Application\Message\BootstrapCrmGithubSync;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Crm\Transport\Request\PostCrmGithubBootstrapSyncRequest;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm Github')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PostCrmGithubBootstrapSyncController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmRequestHandler $crmRequestHandler,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/v1/crm/applications/{applicationSlug}/github/sync/bootstrap', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(
        summary: 'Queue CRM GitHub bootstrap sync',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_ACCEPTED, description: 'Job de synchronisation planifié.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $this->scopeResolver->resolveOrFail($applicationSlug);

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, PostCrmGithubBootstrapSyncRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $jobId = Uuid::uuid4()->toString();

        $this->messageBus->dispatch(new BootstrapCrmGithubSync(
            jobId: $jobId,
            applicationSlug: $applicationSlug,
            token: (string)$input->token,
            owner: (string)$input->owner,
            issueTarget: $input->issueTarget,
            createPublicProject: $input->createPublicProject,
            dryRun: $input->dryRun,
        ));

        $response = [
            'jobId' => $jobId,
            'status' => 'queued',
        ];

        if ($input->dryRun) {
            $response['summary'] = [
                'mode' => 'dry-run',
                'owner' => $input->owner,
                'issueTarget' => $input->issueTarget,
                'createPublicProject' => $input->createPublicProject,
                'plannedActions' => [
                    'Scan repositories and issues from the configured owner.',
                    'Map or create CRM entities from GitHub metadata.',
                    'No persistence changes will be committed in dry-run mode.',
                ],
            ];
        }

        return new JsonResponse($response, JsonResponse::HTTP_ACCEPTED);
    }
}
