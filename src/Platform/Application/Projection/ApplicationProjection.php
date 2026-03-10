<?php

declare(strict_types=1);

namespace App\Platform\Application\Projection;

final class ApplicationProjection
{
    final public const string INDEX_NAME = 'platform_application_v1';

    /**
     * @return array<string, mixed>
     */
    public static function mapping(): array
    {
        return [
            'properties' => [
                'id' => [
                    'type' => 'keyword',
                ],
                'title' => [
                    'type' => 'text',
                ],
                'description' => [
                    'type' => 'text',
                ],
                'slug' => [
                    'type' => 'keyword',
                ],
                'platformName' => [
                    'type' => 'text',
                ],
                'platformKey' => [
                    'type' => 'keyword',
                ],
                'status' => [
                    'type' => 'keyword',
                ],
                'private' => [
                    'type' => 'boolean',
                ],
                'updatedAt' => [
                    'type' => 'date',
                ],
            ],
        ];
    }
}
