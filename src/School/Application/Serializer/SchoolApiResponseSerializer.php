<?php

declare(strict_types=1);

namespace App\School\Application\Serializer;

final readonly class SchoolApiResponseSerializer
{
    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed>|null $pagination
     * @param array<string,mixed>|null $meta
     *
     * @return array<string,mixed>
     */
    public function list(array $items, ?array $pagination = null, ?array $meta = null): array
    {
        $response = [
            'items' => $items,
        ];

        if ($pagination !== null) {
            $response['pagination'] = $pagination;
        }

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return $response;
    }
}
