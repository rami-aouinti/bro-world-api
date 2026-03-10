<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\Auth;

use App\General\Domain\Service\Interfaces\MailerServiceInterface;
use App\General\Domain\Utils\JSON;
use App\Role\Application\Security\Interfaces\RolesServiceInterface;
use App\User\Application\Security\SecurityUser;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\Interfaces\UserRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

use function explode;
use function sprintf;
use function trim;

#[AsController]
class RegisterController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly MailerServiceInterface $mailerService,
        private readonly UserRepositoryInterface $userRepository,
        private readonly RolesServiceInterface $rolesService,
        #[Autowire('%env(resolve:APP_SENDER_EMAIL)%')]
        private readonly string $appSenderEmail,
    ) {
    }

    /**
     * @throws JsonException
     * @throws Throwable
     */
    #[Route(path: '/v1/auth/register', methods: [Request::METHOD_POST])]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = JSON::decode($request->getContent(), true);

        $email = trim((string)($payload['email'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $repeatPassword = (string)($payload['repeatPassword'] ?? '');

        if ($email === '' || $password === '' || $repeatPassword === '') {
            throw new BadRequestHttpException('email, password and repeatPassword are required');
        }

        if ($password !== $repeatPassword) {
            throw new BadRequestHttpException('password and repeatPassword must match');
        }

        if (!$this->userRepository->isEmailAvailable($email)) {
            throw new BadRequestHttpException('Email is already used');
        }

        $user = new User();
        $user
            ->setEmail($email)
            ->setUsername($this->createAvailableUsername($email))
            ->setFirstName('User')
            ->setLastName('User')
            ->setPlainPassword($password);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->mailerService->sendMail(
            'Welcome to Bro World',
            $this->appSenderEmail,
            $email,
            '<p>Your account was successfully created.</p>',
        );

        $securityUser = new SecurityUser($user, $this->rolesService->getInheritedRoles($user->getRoles()));

        return new JsonResponse([
            'token' => $this->jwtTokenManager->create($securityUser),
        ], Response::HTTP_CREATED);
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
}
