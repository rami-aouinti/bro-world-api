<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\User;

use App\Role\Domain\Enum\Role;
use App\User\Application\Resource\UserGroupResource;
use App\User\Application\Resource\UserResource;
use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserGroup;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

/**
 * @package App\User
 */
#[AsController]
#[OA\Tag(name: 'User Management')]
class AttachUserGroupController
{
    public function __construct(
        private readonly UserResource $userResource,
        private readonly UserGroupResource $userGroupResource,
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * Attach specified user group to specified user, accessible only for 'ROLE_ROOT' users.
     *
     * @throws Throwable
     */
    #[Route(
        path: '/v1/user/{user}/group/{userGroup}',
        requirements: [
            'user' => Requirement::UUID_V1,
            'userGroup' => Requirement::UUID_V1,
        ],
        methods: [Request::METHOD_POST],
    )]
    #[OA\Post(summary: 'POST /v1/user/{user}/group/{userGroup}', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['payload'], properties: [new OA\Property(property: 'payload', type: 'object', example: ['value' => 'example'])], example: ['payload' => ['value' => 'example']])), tags: ['User Management'], parameters: [new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'userGroup', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 201, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    #[IsGranted(Role::ROOT->value)]
    #[OA\Parameter(name: 'user', description: 'User GUID', in: 'path', required: true)]
    #[OA\Parameter(name: 'userGroup', description: 'User Group GUID', in: 'path', required: true)]
    public function __invoke(User $user, UserGroup $userGroup): JsonResponse
    {
        $status = $user->getUserGroups()->contains($userGroup) ? Response::HTTP_OK : Response::HTTP_CREATED;
        $this->userResource->save($user->addUserGroup($userGroup), false);
        $this->userGroupResource->save($userGroup, true, true);
        $groups = [
            'groups' => [
                UserGroup::SET_USER_GROUP_BASIC,
            ],
        ];

        return new JsonResponse(
            $this->serializer->serialize($user->getUserGroups()->getValues(), 'json', $groups),
            $status,
            json: true
        );
    }
}
