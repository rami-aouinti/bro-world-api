<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Tag;

use App\General\Application\DTO\Interfaces\RestDtoInterface;
use App\General\Application\Exception\ValidatorException;
use App\General\Transport\Rest\Controller;
use App\General\Transport\Rest\Traits\Methods\CreateMethod;
use App\Recruit\Application\DTO\Tag\TagCreate;
use App\Recruit\Application\Resource\TagResource;
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
#[OA\Tag(name: 'Recruit Management')]
class TagCreateController extends Controller
{
    use CreateMethod;

    public function __construct(
        TagResource $resource,
        private readonly AutoMapperInterface $autoMapper,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct($resource);
    }

    #[Route(
        path: '/v1/recruit/tag',
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted('ROLE_ROOT')]
    #[OA\Post(summary: 'Create tag', responses: [new OA\Response(response: 201, description: 'created')])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object'))]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request): Response
    {
        $request->attributes->set('applicationSlug', $applicationSlug);

        return $this->createMethod($request, $this->mapAndValidateDto($request, TagCreate::class));
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
