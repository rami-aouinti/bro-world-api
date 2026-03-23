<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Contact;

use App\Crm\Application\Dto\Command\CreateContactCommandDto;
use App\Crm\Application\Dto\Response\EntityIdResponseDto;
use App\Crm\Application\Message\CreateContactCommand;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Contact;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_ADMIN->value)]
final readonly class CreateContactController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmRequestHandler $crmRequestHandler,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/contacts', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(
        summary: 'Create Contact dans le CRM',
        description: 'Exécute l action metier Create Contact dans le perimetre de l application CRM.',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_CREATED, description: 'Ressource créée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateContactCommandDto::class, mapperMethod: 'fromPostArray');
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $contact = (new Contact())
            ->setFirstName((string)$input->firstName)
            ->setLastName((string)$input->lastName)
            ->setEmail($input->email)
            ->setPhone($input->phone)
            ->setJobTitle($input->jobTitle)
            ->setCity($input->city)
            ->setScore($input->score ?? 0);

        $companyId = ($input->companyId ?? '') !== '' ? (string)$input->companyId : null;

        $this->messageBus->dispatch(new CreateContactCommand(
            id: $contact->getId(),
            crmId: $crm->getId(),
            firstName: $contact->getFirstName(),
            lastName: $contact->getLastName(),
            email: $contact->getEmail(),
            phone: $contact->getPhone(),
            jobTitle: $contact->getJobTitle(),
            city: $contact->getCity(),
            score: $contact->getScore(),
            companyId: $companyId,
            applicationSlug: $applicationSlug,
        ));

        return new JsonResponse((new EntityIdResponseDto($contact->getId()))->toArray(), JsonResponse::HTTP_CREATED);
    }
}
