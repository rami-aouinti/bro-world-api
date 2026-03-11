<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\General\Transport\Rest\Controller;
use App\General\Application\DTO\Interfaces\RestDtoInterface;
use App\General\Application\Exception\ValidatorException;
use App\Recruit\Application\DTO\Job\JobCreate;
use App\Recruit\Application\Resource\JobResource;
use AutoMapperPlus\AutoMapperInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function count;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Recruit Job Management')]
class JobCreateController extends Controller
{
    public function __construct(
        JobResource $resource,
        private readonly AutoMapperInterface $autoMapper,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct($resource);
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

    #[Route(
        path: '/v1/recruit/job',
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_ROOT')]
    #[OA\Post(summary: 'Create job', responses: [new OA\Response(response: 201, description: 'created')])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    public function __invoke(Request $request): Response
    {
        return $this->createMethod($request, $this->mapAndValidateDto($request, JobCreate::class));
    }
}
