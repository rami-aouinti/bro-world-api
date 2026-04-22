<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\Billing;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_numeric;
use function is_string;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PatchGeneralBillingController
{
    use GeneralCrudApiTrait;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[OA\Patch(summary: 'General - Update Billing', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: ['status' => 'paid', 'paidAt' => '2026-05-02T08:30:00+00:00'])), responses: [new OA\Response(response: 200, description: 'Billing mise à jour', content: new OA\JsonContent(example: ['id' => 'uuid']))])]
    public function __invoke(Billing $billing, Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if (isset($payload['label']) && is_string($payload['label']) && $payload['label'] !== '') {
            $billing->setLabel($payload['label']);
        }

        if (isset($payload['amount']) && is_numeric($payload['amount'])) {
            $billing->setAmount((float) $payload['amount']);
        }

        if (isset($payload['currency'])) {
            $billing->setCurrency($this->nullableString($payload['currency']) ?? 'EUR');
        }

        if (isset($payload['status'])) {
            $billing->setStatus($this->nullableString($payload['status']) ?? 'pending');
        }

        if (isset($payload['dueAt'])) {
            $billing->setDueAt($this->parseNullableDate($payload['dueAt']));
        }

        if (isset($payload['paidAt'])) {
            $billing->setPaidAt($this->parseNullableDate($payload['paidAt']));
        }

        $this->entityManager->flush();

        return new JsonResponse(['id' => $billing->getId()]);
    }
}
