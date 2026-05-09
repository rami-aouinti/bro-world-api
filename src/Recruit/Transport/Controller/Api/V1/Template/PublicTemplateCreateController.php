<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Template;

use App\Recruit\Domain\Entity\Template;
use App\Recruit\Infrastructure\Repository\TemplateRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Recruit Template')]
readonly class PublicTemplateCreateController
{
    public function __construct(
        private TemplateRepository $templateRepository,
    ) {
    }

    #[Route(path: '/v1/recruit/templates/resumes', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Crée un template public de type resume et retourne son id.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name'], properties: [new OA\Property(property: 'name', type: 'string', example: 'Modern Resume v1'), new OA\Property(property: 'version', type: 'integer', example: 1), new OA\Property(property: 'layout', type: 'string', example: 'single_column'), new OA\Property(property: 'sections', type: 'array', items: new OA\Items(type: 'string'), example: ['summary', 'experience', 'education', 'skills'])], type: 'object'))]
    #[OA\Response(response: 201, description: 'Template créé.', content: new OA\JsonContent(properties: [new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')], type: 'object'))]
    public function createResumeTemplate(Request $request): JsonResponse
    {
        return $this->createTemplate($request, 'resume');
    }

    #[Route(path: '/v1/recruit/templates/cover-pages', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Crée un template public de type cover_page et retourne son id.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name'], properties: [new OA\Property(property: 'name', type: 'string', example: 'Clean Cover Page v1'), new OA\Property(property: 'version', type: 'integer', example: 1), new OA\Property(property: 'layout', type: 'string', example: 'left_photo'), new OA\Property(property: 'sections', type: 'array', items: new OA\Items(type: 'string'), example: ['profile', 'contact'])], type: 'object'))]
    #[OA\Response(response: 201, description: 'Template créé.', content: new OA\JsonContent(properties: [new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440001')], type: 'object'))]
    public function createCoverPageTemplate(Request $request): JsonResponse
    {
        return $this->createTemplate($request, 'cover_page');
    }

    #[Route(path: '/v1/recruit/templates/cover-letters', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Crée un template public de type cover_letter et retourne son id.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name'], properties: [new OA\Property(property: 'name', type: 'string', example: 'Professional Cover Letter v1'), new OA\Property(property: 'version', type: 'integer', example: 1), new OA\Property(property: 'layout', type: 'string', example: 'classic_letter'), new OA\Property(property: 'sections', type: 'array', items: new OA\Items(type: 'string'), example: ['intro', 'body', 'closing'])], type: 'object'))]
    #[OA\Response(response: 201, description: 'Template créé.', content: new OA\JsonContent(properties: [new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440002')], type: 'object'))]
    public function createCoverLetterTemplate(Request $request): JsonResponse
    {
        return $this->createTemplate($request, 'cover_letter');
    }

    private function createTemplate(Request $request, string $type): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "name" is required.');
        }

        $template = (new Template())
            ->setName($name)
            ->setType($type)
            ->setVersion((int) ($payload['version'] ?? 1))
            ->setLayout(isset($payload['layout']) ? (string) $payload['layout'] : null)
            ->setStructure(isset($payload['structure']) ? (string) $payload['structure'] : null)
            ->setSections(is_array($payload['sections'] ?? null) ? $payload['sections'] : null)
            ->setTheme(is_array($payload['theme'] ?? null) ? $payload['theme'] : null)
            ->setAside(is_array($payload['aside'] ?? null) ? $payload['aside'] : null)
            ->setPhoto(is_array($payload['photo'] ?? null) ? $payload['photo'] : null)
            ->setDecor(is_array($payload['decor'] ?? null) ? $payload['decor'] : null)
            ->setLayoutOptions(is_array($payload['layoutOptions'] ?? null) ? $payload['layoutOptions'] : null)
            ->setDecorOptions(is_array($payload['decorOptions'] ?? null) ? $payload['decorOptions'] : null)
            ->setSectionTitleStyle(is_array($payload['sectionTitleStyle'] ?? null) ? $payload['sectionTitleStyle'] : null)
            ->setHeaderType(isset($payload['headerType']) ? (string) $payload['headerType'] : null)
            ->setFakeData(is_array($payload['fakeData'] ?? null) ? $payload['fakeData'] : null)
            ->setTextStyles(is_array($payload['textStyles'] ?? null) ? $payload['textStyles'] : null)
            ->setTypography(is_array($payload['typography'] ?? null) ? $payload['typography'] : null)
            ->setSectionBar(is_array($payload['sectionBar'] ?? null) ? $payload['sectionBar'] : null)
            ->setItems(is_array($payload['items'] ?? null) ? $payload['items'] : null)
            ->setPhotoOptions(is_array($payload['photoOptions'] ?? null) ? $payload['photoOptions'] : null)
            ->setLevelStyle(is_array($payload['levelStyle'] ?? null) ? $payload['levelStyle'] : null)
            ->setSectionOrder(is_array($payload['sectionOrder'] ?? null) ? $payload['sectionOrder'] : null)
            ->setSectionTypes(is_array($payload['sectionTypes'] ?? null) ? $payload['sectionTypes'] : null)
            ->setHero(is_array($payload['hero'] ?? null) ? $payload['hero'] : null)
            ->setDesignTokens(is_array($payload['designTokens'] ?? null) ? $payload['designTokens'] : null)
            ->setDesignConfig(is_array($payload['designConfig'] ?? null) ? $payload['designConfig'] : null)
            ->setDefaultValues(is_array($payload['defaultValues'] ?? null) ? $payload['defaultValues'] : null);

        $this->templateRepository->save($template);

        return new JsonResponse(['id' => $template->getId()], JsonResponse::HTTP_CREATED);
    }
}
