<?php

declare(strict_types=1);

namespace App\Tool\Transport\Controller\Api;

use App\General\Application\Service\MercurePublisher;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class MercureTestController
{
    #[Route('/mercure/test', name: 'mercure_test', methods: [Request::METHOD_GET])]
    public function mercureTest(MercurePublisher $mercurePublisher): JsonResponse
    {
        $mercurePublisher->publish(
            '/debug/mercure',
            [
                'test' => 'hello debug',
                'time' => (new DateTimeImmutable())->format(DATE_ATOM),
            ],
            false,
        );

        return new JsonResponse(['ok' => true]);
    }
}
