<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\Billing;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_numeric;
use function is_string;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class CreateGeneralBillingController
{
    use GeneralCrudApiTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CompanyRepository $companyRepository,
    ) {
    }

    #[OA\Post(
        summary: 'General - Create Billing',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: ['companyId' => 'uuid', 'label' => 'Licence', 'amount' => 149.90, 'currency' => 'EUR', 'status' => 'pending', 'dueAt' => '2026-05-01T10:00:00+00:00'])),
        responses: [new OA\Response(response: 201, description: 'Billing créée', content: new OA\JsonContent(example: ['id' => 'uuid']))],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $companyId = $payload['companyId'] ?? null;
        $label = $payload['label'] ?? null;
        $amount = $payload['amount'] ?? null;

        if (!is_string($companyId) || !is_string($label) || !is_numeric($amount)) {
            return $this->badRequest('Fields "companyId", "label" and "amount" are required.');
        }

        $company = $this->companyRepository->find($companyId);
        if ($company === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Company not found.');
        }

        $billing = (new Billing())
            ->setCompany($company)
            ->setLabel($label)
            ->setAmount((float) $amount)
            ->setCurrency($this->nullableString($payload['currency'] ?? null) ?? 'EUR')
            ->setStatus($this->nullableString($payload['status'] ?? null) ?? 'pending')
            ->setDueAt($this->parseNullableDate($payload['dueAt'] ?? null))
            ->setPaidAt($this->parseNullableDate($payload['paidAt'] ?? null));

        $this->entityManager->persist($billing);
        $this->entityManager->flush();

        return new JsonResponse(['id' => $billing->getId()], JsonResponse::HTTP_CREATED);
    }
}
