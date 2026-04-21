<?php

declare(strict_types=1);

namespace App\Page\Transport\Controller\Api\V1\Public;

use App\Page\Application\Service\PublicContactMutationService;
use App\Page\Application\Service\PublicPageReadService;
use JsonException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

use function filter_var;
use function sprintf;
use function trim;

#[AsController]
#[OA\Tag(name: 'Page Public')]
final readonly class PublicPageController
{
    public function __construct(
        private PublicPageReadService $publicPageReadService,
        private PublicContactMutationService $publicContactMutationService,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route(path: '/v1/page/public/home/{languageCode}', methods: [Request::METHOD_GET])]
    #[OA\Get(security: [])]
    public function home(string $languageCode): JsonResponse
    {
        return $this->jsonContentOr404($this->publicPageReadService->getHome($languageCode), $languageCode);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route(path: '/v1/page/public/about/{languageCode}', methods: [Request::METHOD_GET])]
    #[OA\Get(security: [])]
    public function about(string $languageCode): JsonResponse
    {
        return $this->jsonContentOr404($this->publicPageReadService->getAbout($languageCode), $languageCode);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route(path: '/v1/page/public/contact/{languageCode}', methods: [Request::METHOD_GET])]
    #[OA\Get(security: [])]
    public function contact(string $languageCode): JsonResponse
    {
        return $this->jsonContentOr404($this->publicPageReadService->getContact($languageCode), $languageCode);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route(path: '/v1/page/public/faq/{languageCode}', methods: [Request::METHOD_GET])]
    #[OA\Get(security: [])]
    public function faq(string $languageCode): JsonResponse
    {
        return $this->jsonContentOr404($this->publicPageReadService->getFaq($languageCode), $languageCode);
    }

    #[Route(path: '/v1/page/public/contact', methods: [Request::METHOD_POST])]
    #[OA\Post(security: [], summary: 'Create a public contact request.')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['firstName', 'lastName', 'email', 'type', 'message'],
            properties: [
                new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john.doe@example.com'),
                new OA\Property(property: 'type', type: 'string', example: 'support'),
                new OA\Property(property: 'message', type: 'string', example: 'Hello, I need help with my account.'),
            ],
        ),
    )]
    #[OA\Response(response: Response::HTTP_CREATED, description: 'Contact request created.')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid payload.')]
    public function createContact(Request $request): JsonResponse
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestHttpException('Invalid JSON payload.');
        }

        $normalizedPayload = $this->normalizeContactPayload($payload);
        $contactRequest = $this->publicContactMutationService->create($normalizedPayload);

        return new JsonResponse([
            'id' => $contactRequest->getId(),
            'status' => 'created',
        ], Response::HTTP_CREATED);
    }

    /**
     * @param array<string, mixed>|null $content
     */
    private function jsonContentOr404(?array $content, string $languageCode): JsonResponse
    {
        if ($content === null) {
            if ($this->publicPageReadService->resolveLanguage($languageCode) === null) {
                throw new NotFoundHttpException('Language not found.');
            }

            throw new NotFoundHttpException('Page content not found.');
        }

        return new JsonResponse($content);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{firstName: string, lastName: string, email: string, type: string, message: string}
     */
    private function normalizeContactPayload(array $payload): array
    {
        $normalizedPayload = [
            'firstName' => trim((string)($payload['firstName'] ?? '')),
            'lastName' => trim((string)($payload['lastName'] ?? '')),
            'email' => trim((string)($payload['email'] ?? '')),
            'type' => trim((string)($payload['type'] ?? '')),
            'message' => trim((string)($payload['message'] ?? '')),
        ];

        foreach ($normalizedPayload as $field => $value) {
            if ($value === '') {
                throw new BadRequestHttpException(sprintf('%s is required.', $field));
            }
        }

        if (filter_var($normalizedPayload['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new BadRequestHttpException('email must be valid.');
        }

        return $normalizedPayload;
    }
}
