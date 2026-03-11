<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Company;

use App\Crm\Domain\Entity\Company;
use App\Crm\Infrastructure\Repository\CrmRepository;
use App\General\Application\Message\EntityCreated;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class CreateCompanyController
{
    public function __construct(
        private CrmRepository $crmRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/companies', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'POST /v1/crm/companies', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['payload'], properties: [new OA\Property(property: 'payload', type: 'object', example: ['value' => 'example'])], example: ['payload' => ['value' => 'example']])))]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = (array) json_decode((string) $request->getContent(), true);
        $company = new Company();
        $company->setName((string) ($payload['name'] ?? ''))
            ->setIndustry(isset($payload['industry']) ? (string) $payload['industry'] : null)
            ->setWebsite(isset($payload['website']) ? (string) $payload['website'] : null)
            ->setContactEmail(isset($payload['contactEmail']) ? (string) $payload['contactEmail'] : null)
            ->setPhone(isset($payload['phone']) ? (string) $payload['phone'] : null);
        if (is_string($payload['crmId'] ?? null)) {
            $company->setCrm($this->crmRepository->find($payload['crmId']));
        }

        $this->entityManager->persist($company);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_company', $company->getId()));

        return new JsonResponse(['id' => $company->getId()], JsonResponse::HTTP_CREATED);
    }
}
