<?php
declare(strict_types=1);
namespace App\School\Transport\Controller\Api\V1\School;
use App\General\Application\Message\EntityPatched;
use App\School\Application\Service\SchoolResourcePatchService;
use App\School\Application\Service\SchoolResourceViewService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[AsController]
#[OA\Tag(name: 'School')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class PatchSchoolResourceController
{
    public function __construct(
        private SchoolResourceViewService $resourceViewService,
        private SchoolResourcePatchService $resourcePatchService,
        private MessageBusInterface $messageBus,
    ) {}
    #[Route('/v1/school/{resource}/{id}', methods: [Request::METHOD_PATCH], requirements: ['resource' => 'classes|students|teachers|exams|grades'])]
    public function __invoke(string $resource, string $id, Request $request): JsonResponse
    {
        $entity = $this->resourceViewService->findOr404($resource, $id);
        $this->resourcePatchService->patch($entity, $resource, $request->toArray());
        $this->messageBus->dispatch(new EntityPatched('school_' . substr($resource, 0, -1), $id));
        return new JsonResponse($this->resourceViewService->map($entity));
    }
}
