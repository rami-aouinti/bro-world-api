<?php

declare(strict_types=1);

namespace App\Page\Transport\Controller\Api\V1\Public;

use App\Page\Application\Service\PublicPageReadService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Page Public')]
final class PublicPageController
{
    public function __construct(
        private readonly PublicPageReadService $publicPageReadService,
    ) {
    }

    #[Route(path: '/v1/page/public/home/{languageCode}', methods: [Request::METHOD_GET])]
    #[OA\Get(security: [])]
    public function home(string $languageCode): JsonResponse
    {
        return $this->jsonContentOr404($this->publicPageReadService->getHome($languageCode), $languageCode);
    }

    #[Route(path: '/v1/page/public/about/{languageCode}', methods: [Request::METHOD_GET])]
    #[OA\Get(security: [])]
    public function about(string $languageCode): JsonResponse
    {
        return $this->jsonContentOr404($this->publicPageReadService->getAbout($languageCode), $languageCode);
    }

    #[Route(path: '/v1/page/public/contact/{languageCode}', methods: [Request::METHOD_GET])]
    #[OA\Get(security: [])]
    public function contact(string $languageCode): JsonResponse
    {
        return $this->jsonContentOr404($this->publicPageReadService->getContact($languageCode), $languageCode);
    }

    #[Route(path: '/v1/page/public/faq/{languageCode}', methods: [Request::METHOD_GET])]
    #[OA\Get(security: [])]
    public function faq(string $languageCode): JsonResponse
    {
        return $this->jsonContentOr404($this->publicPageReadService->getFaq($languageCode), $languageCode);
    }

    /** @param array<string, mixed>|null $content */
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
}
