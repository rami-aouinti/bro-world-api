<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruit\Application\Service;

use App\Recruit\Application\Service\ResumePayloadService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ResumePayloadServiceTest extends TestCase
{
    public function testExtractPayloadRejectsInvalidJsonSectionInMultipartPayload(): void
    {
        $service = new ResumePayloadService($this->createMock(EntityManagerInterface::class));

        $request = new Request([], [
            'skills' => 'string',
        ]);

        try {
            $service->extractPayload($request);
            self::fail('Expected HttpException was not thrown.');
        } catch (HttpException $exception) {
            self::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
            self::assertSame('Field "skills" must be a valid JSON array string.', $exception->getMessage());
        }
    }

    public function testExtractPayloadTreatsEmptySectionStringAsEmptyArray(): void
    {
        $service = new ResumePayloadService($this->createMock(EntityManagerInterface::class));

        $request = new Request([], [
            'skills' => '   ',
        ]);

        $payload = $service->extractPayload($request);

        self::assertArrayHasKey('skills', $payload);
        self::assertSame([], $payload['skills']);
    }
}
