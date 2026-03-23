<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Contact;

use App\Crm\Application\Dto\Command\UpdateContactCommandDto;
use App\Crm\Application\Dto\Response\EntityIdResponseDto;
use App\Crm\Application\Exception\CrmReferenceNotFoundException;
use App\Crm\Application\Message\PatchContactCommand;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_ADMIN->value)]
final readonly class PatchContactController
{
    public function __construct(
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmRequestHandler $crmRequestHandler,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/v1/crm/applications/{applicationSlug}/contacts/{id}', methods: [Request::METHOD_PATCH])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Patch(
        description: 'Exécute l action metier Patch Contact dans le perimetre de l application CRM.',
        summary: 'Patch Contact',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Opération exécutée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $applicationSlug, string $id, Request $request): JsonResponse
    {
        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, UpdateContactCommandDto::class, mapperMethod: 'fromPatchArray');
        if ($input instanceof JsonResponse) {
            return $input;
        }

        try {
            $mappedPayload = [];
            if ($input->hasFirstName) {
                $mappedPayload['firstName'] = $input->firstName;
            }
            if ($input->hasLastName) {
                $mappedPayload['lastName'] = $input->lastName;
            }
            if ($input->hasEmail) {
                $mappedPayload['email'] = $input->email;
            }
            if ($input->hasPhone) {
                $mappedPayload['phone'] = $input->phone;
            }
            if ($input->hasJobTitle) {
                $mappedPayload['jobTitle'] = $input->jobTitle;
            }
            if ($input->hasCity) {
                $mappedPayload['city'] = $input->city;
            }
            if ($input->hasScore) {
                $mappedPayload['score'] = $input->score;
            }
            if ($input->hasCompanyId) {
                $mappedPayload['companyId'] = $input->companyId;
            }

            $this->messageBus->dispatch(new PatchContactCommand(
                applicationSlug: $applicationSlug,
                contactId: $id,
                payload: $mappedPayload,
            ));
        } catch (CrmReferenceNotFoundException $exception) {
            return $this->errorResponseFactory->notFoundReference($exception->field);
        }

        return new JsonResponse(new EntityIdResponseDto($id)->toArray());
    }
}
