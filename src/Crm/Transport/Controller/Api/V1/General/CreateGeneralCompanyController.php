<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\Company;
use App\Crm\Infrastructure\Repository\CrmRepository;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_string;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class CreateGeneralCompanyController
{
    use GeneralCrudApiTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CrmRepository $crmRepository,
    ) {
    }

    #[OA\Post(
        summary: 'General - Create Company',
        description: 'Crée une company CRM dans le scope général.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: [
            'crmId' => 'e8ab2bce-35b4-4f95-8f86-6de24d520a00',
            'name' => 'Acme Corporation',
            'industry' => 'SaaS',
            'website' => 'https://acme.test',
            'contactEmail' => 'contact@acme.test',
            'phone' => '+33102030405',
        ])),
        responses: [new OA\Response(response: 201, description: 'Company créée', content: new OA\JsonContent(example: ['id' => '3b7044ba-1f2e-4f62-b07f-cf8d77ccf970']))],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $crmId = $payload['crmId'] ?? null;
        $name = $payload['name'] ?? null;
        if (!is_string($crmId) || !is_string($name) || $name === '') {
            return $this->badRequest('Fields "crmId" and "name" are required.');
        }

        $crm = $this->crmRepository->find($crmId);
        if ($crm === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'CRM not found.');
        }

        $company = (new Company())
            ->setCrm($crm)
            ->setName($name)
            ->setIndustry($this->nullableString($payload['industry'] ?? null))
            ->setWebsite($this->nullableString($payload['website'] ?? null))
            ->setContactEmail($this->nullableString($payload['contactEmail'] ?? null))
            ->setPhone($this->nullableString($payload['phone'] ?? null));

        $this->entityManager->persist($company);
        $this->entityManager->flush();

        return new JsonResponse(['id' => $company->getId()], JsonResponse::HTTP_CREATED);
    }
}
