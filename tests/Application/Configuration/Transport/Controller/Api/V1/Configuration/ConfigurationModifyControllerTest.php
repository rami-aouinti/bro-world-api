<?php

declare(strict_types=1);

namespace App\Tests\Application\Configuration\Transport\Controller\Api\V1\Configuration;

use App\Configuration\Infrastructure\DataFixtures\ORM\LoadConfigurationData;
use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ConfigurationModifyControllerTest extends WebTestCase
{
    private string $baseUrl = self::API_URL_PREFIX . '/v1/configuration';

    /**
     * @throws Throwable
     */
    #[TestDox('Test that patch/delete configuration actions are accepted asynchronously.')]
    public function testThatPatchAndDeleteActionsAreAcceptedAsynchronously(): void
    {
        $configurationId = LoadConfigurationData::getUuidByKey('platform-secrets');

        $rootClient = $this->getTestClient('john-root', 'password-root');

        $patchData = [
            'configurationValue' => [
                'apiSecret' => 'patched-secret',
                'rotation' => 15,
            ],
        ];
        $rootClient->request('PATCH', $this->baseUrl . '/' . $configurationId, content: JSON::encode($patchData));
        $patchResponse = $rootClient->getResponse();
        $patchContent = $patchResponse->getContent();
        self::assertNotFalse($patchContent);
        self::assertSame(Response::HTTP_ACCEPTED, $patchResponse->getStatusCode(), "Response:\n" . $patchResponse);
        $patched = JSON::decode($patchContent, true);
        self::assertArrayHasKey('operationId', $patched);
        self::assertSame($configurationId, $patched['id']);

        $rootClient->request('DELETE', $this->baseUrl . '/' . $configurationId);
        $deleteResponse = $rootClient->getResponse();
        $deleteContent = $deleteResponse->getContent();
        self::assertNotFalse($deleteContent);
        self::assertSame(Response::HTTP_ACCEPTED, $deleteResponse->getStatusCode(), "Response:\n" . $deleteResponse);
        $deleted = JSON::decode($deleteContent, true);
        self::assertArrayHasKey('operationId', $deleted);
        self::assertSame($configurationId, $deleted['id']);
    }
}
