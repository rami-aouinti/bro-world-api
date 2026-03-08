<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class SchoolController
{
    #[Route('/v1/school', methods: [Request::METHOD_GET])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['module' => 'school', 'status' => 'ok']);
    }
}
