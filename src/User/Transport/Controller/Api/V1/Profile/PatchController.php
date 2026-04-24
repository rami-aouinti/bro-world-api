<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\Profile;

use App\General\Domain\Enum\Language;
use App\General\Domain\Enum\Locale;
use App\General\Domain\Utils\JSON;
use App\User\Application\Resource\UserResource;
use App\User\Domain\Entity\User;
use JsonException;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\JsonContent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

use function in_array;
use function is_string;

/**
 * @package App\User
 */
#[AsController]
#[OA\Tag(name: 'Profile')]
class PatchController
{
    private const ALLOWED_FIELDS = [
        'username',
        'firstName',
        'lastName',
        'email',
        'language',
        'locale',
        'visible',
        'abonnement',
    ];

    public function __construct(
        private readonly UserResource $userResource,
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * Patch current user profile data, accessible only for 'IS_AUTHENTICATED_FULLY' users.
     *
     * @throws JsonException
     */
    #[Route(
        path: '/v1/profile',
        methods: [Request::METHOD_PATCH],
    )]
    #[OA\Patch(summary: 'PATCH /v1/profile', tags: ['Profile'], parameters: [], responses: [new OA\Response(response: 200, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    #[OA\RequestBody(
        required: true,
        content: new JsonContent(
            type: 'object',
            example: [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'timezone' => 'Europe/Helsinki',
            ],
        ),
    )]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();

        foreach ($payload as $field => $value) {
            if (!in_array($field, self::ALLOWED_FIELDS, true)) {
                throw new HttpException(
                    JsonResponse::HTTP_BAD_REQUEST,
                    'Invalid field "' . $field . '" in request body.',
                );
            }

            $this->applyPatch($loggedInUser, $field, $value);
        }

        $this->userResource->save($loggedInUser);

        /** @var array<string, mixed> $output */
        $output = JSON::decode(
            $this->serializer->serialize(
                $loggedInUser,
                'json',
                [
                    'groups' => User::SET_USER_PROFILE,
                ],
            ),
            true,
        );

        return new JsonResponse($output);
    }

    private function applyPatch(User $loggedInUser, string $field, mixed $value): void
    {
        if ($field === 'visible' || $field === 'abonnement') {
            if (!is_bool($value)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "' . $field . '" must be a boolean.');
            }

            if ($field === 'visible') {
                $loggedInUser->setVisible($value);

                return;
            }

            $loggedInUser->setAbonnement($value);

            return;
        }

        if (!is_string($value)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "' . $field . '" must be a string.');
        }

        match ($field) {
            'username' => $loggedInUser->setUsername($value),
            'firstName' => $loggedInUser->setFirstName($value),
            'lastName' => $loggedInUser->setLastName($value),
            'email' => $loggedInUser->setEmail($value),
            'language' => $loggedInUser->setLanguage(
                Language::tryFrom($value) ?? throw new HttpException(
                    JsonResponse::HTTP_BAD_REQUEST,
                    'Invalid language value.',
                ),
            ),
            'locale' => $loggedInUser->setLocale(
                Locale::tryFrom($value) ?? throw new HttpException(
                    JsonResponse::HTTP_BAD_REQUEST,
                    'Invalid locale value.',
                ),
            ),
            default => throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unsupported field "' . $field . '".'),
        };
    }

}
