<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\General\Application\DTO\Interfaces\RestDtoInterface;
use App\General\Application\Exception\ValidatorException;
use App\General\Transport\Rest\Controller;
use App\Recruit\Application\DTO\Job\JobUpdate;
use App\Recruit\Application\Resource\JobResource;
use AutoMapperPlus\AutoMapperInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function count;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Recruit Management')]
class JobUpdateController extends Controller
{
    public function __construct(
        JobResource $resource,
        private readonly AutoMapperInterface $autoMapper,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct($resource);
    }

    #[Route(
        path: '/v1/recruit/applications/{applicationSlug}/job/{id}',
        requirements: [
            'id' => Requirement::UUID_V1,
        ],
        methods: [Request::METHOD_PUT],
    )]
    #[IsGranted('ROLE_ROOT')]
    #[OA\Put(summary: 'Update job', responses: [new OA\Response(response: 200, description: 'success')])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request, string $id): Response
    {
        $request->attributes->set('applicationSlug', $applicationSlug);

        return $this->updateMethod($request, $this->mapAndValidateDto($request, JobUpdate::class), $id);
    }

    private function mapAndValidateDto(Request $request, string $dtoClass): RestDtoInterface
    {
        /** @var RestDtoInterface $dto */
        $dto = $this->autoMapper->map($request, $dtoClass);
        $errors = $this->validator->validate($dto);

        if (count($errors) > 0) {
            throw new ValidatorException($dto::class, $errors);
        }

        return $dto;
    }
}
