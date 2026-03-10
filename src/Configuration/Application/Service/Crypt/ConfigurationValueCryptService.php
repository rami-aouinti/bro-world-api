<?php

declare(strict_types=1);

namespace App\Configuration\Application\Service\Crypt;

use App\Configuration\Application\Service\Crypt\Interfaces\ConfigurationValueCryptServiceInterface;
use App\Configuration\Domain\Entity\Configuration;
use App\Tool\Domain\Service\Crypt\Interfaces\OpenSslCryptServiceInterface;

/**
 * @package App\Configuration
 */
class ConfigurationValueCryptService implements ConfigurationValueCryptServiceInterface
{
    public function __construct(
        private readonly OpenSslCryptServiceInterface $openSslCryptService,
    ) {
    }

    public function encryptValue(Configuration $configuration): void
    {
        if (!$configuration->isPrivate() || $configuration->getConfigurationValueParameters() !== null) {
            return;
        }

        $value = $configuration->getConfigurationValue();
        $data = $this->openSslCryptService->encrypt(json_encode($value, JSON_THROW_ON_ERROR));

        $configuration
            ->setConfigurationValue([
                'data' => $data['data'],
            ])
            ->setConfigurationValueParameters($data['params']);
    }

    public function decryptValue(Configuration $configuration): void
    {
        $params = $configuration->getConfigurationValueParameters();

        if (!$configuration->isPrivate() || $params === null) {
            return;
        }

        $value = $configuration->getConfigurationValue();
        $encryptedData = $value['data'] ?? null;

        if (!is_string($encryptedData) || $encryptedData === '') {
            return;
        }

        $decrypted = $this->openSslCryptService->decrypt([
            'data' => $encryptedData,
            'params' => $params,
        ]);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);

        $configuration->setConfigurationValue($decoded);
    }
}
