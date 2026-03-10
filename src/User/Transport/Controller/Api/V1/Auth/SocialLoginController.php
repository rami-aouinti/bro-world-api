<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\Auth;

use OpenApi\Attributes as OA;
use App\General\Domain\Utils\JSON;
use App\Role\Application\Security\Interfaces\RolesServiceInterface;
use App\User\Application\Security\SecurityUser;
use App\User\Domain\Entity\Social;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\Interfaces\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

use function array_contains;
use function bin2hex;
use function explode;
use function random_bytes;
use function sprintf;
use function trim;

#[AsController]
#[OA\Tag(name: 'Authentication')]
class SocialLoginController
{
    /** @var array<int, string> */
    private const array ALLOWED_PROVIDERS = ['github', 'instagram', 'facebook', 'google'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly UserRepositoryInterface $userRepository,
        private readonly RolesServiceInterface $rolesService,
    ) {
    }

    /**
     * @throws JsonException
     * @throws Throwable
     */
    #[Route(path: '/v1/auth/social_login', methods: [Request::METHOD_POST])]
    #[OA\Post(
        summary: 'Authenticate or create a user via a social provider',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'provider', 'providerId'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'social.user@bro-world.com'),
                    new OA\Property(property: 'provider', type: 'string', enum: ['github', 'instagram', 'facebook', 'google'], example: 'google'),
                    new OA\Property(property: 'providerId', type: 'string', example: 'google-oauth2|1134889988776655'),
                ],
                example: [
                    'email' => 'social.user@bro-world.com',
                    'provider' => 'google',
                    'providerId' => 'google-oauth2|1134889988776655',
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Authenticated successfully, token returned.'),
            new OA\Response(response: 400, description: 'Invalid payload or unsupported provider.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = JSON::decode($request->getContent(), true);

        $email = trim((string)($payload['email'] ?? ''));
        $provider = trim((string)($payload['provider'] ?? ''));
        $providerId = trim((string)($payload['providerId'] ?? ''));

        if ($email === '' || $provider === '' || $providerId === '') {
            throw new BadRequestHttpException('email, provider and providerId are required');
        }

        if (!array_contains(self::ALLOWED_PROVIDERS, $provider)) {
            throw new BadRequestHttpException('provider must be one of: github, instagram, facebook, google');
        }

        $social = $this->entityManager
            ->getRepository(Social::class)
            ->createQueryBuilder('social')
            ->select('social', 'user')
            ->innerJoin('social.user', 'user')
            ->andWhere('social.provider = :provider')
            ->andWhere('social.providerId = :providerId')
            ->andWhere('user.email = :email')
            ->setParameter('provider', $provider)
            ->setParameter('providerId', $providerId)
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();

        if (!($social instanceof Social)) {
            $user = $this->userRepository->loadUserByIdentifier($email, false);

            if (!($user instanceof User)) {
                $user = new User();
                $user
                    ->setEmail($email)
                    ->setUsername($this->createAvailableUsername($email))
                    ->setFirstName('User')
                    ->setLastName('User')
                    ->setPlainPassword($this->generateRandomPassword());

                $this->entityManager->persist($user);
            }

            $social = new Social();
            $social
                ->setProvider($provider)
                ->setProviderId($providerId)
                ->setUser($user);

            $this->entityManager->persist($social);
            $this->entityManager->flush();
        }

        $securityUser = new SecurityUser(
            $social->getUser(),
            $this->rolesService->getInheritedRoles($social->getUser()->getRoles()),
        );

        return new JsonResponse([
            'token' => $this->jwtTokenManager->create($securityUser),
        ], Response::HTTP_OK);
    }

    private function createAvailableUsername(string $email): string
    {
        $localPart = trim(explode('@', $email)[0] ?? '');
        $username = $localPart !== '' ? $localPart : 'user';
        $baseUsername = $username;
        $index = 1;

        while (!$this->userRepository->isUsernameAvailable($username)) {
            $username = sprintf('%s%d', $baseUsername, $index);
            ++$index;
        }

        return $username;
    }

    /** @throws Throwable */
    private function generateRandomPassword(): string
    {
        return bin2hex(random_bytes(16));
    }
}
