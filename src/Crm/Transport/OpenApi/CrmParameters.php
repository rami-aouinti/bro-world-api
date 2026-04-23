<?php

declare(strict_types=1);

namespace App\Crm\Transport\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Parameter(parameter: 'page', name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1)]
#[OA\Parameter(parameter: 'limit', name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 20)]
#[OA\Parameter(parameter: 'q', name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Filtre de recherche libre')]
#[OA\Parameter(parameter: 'status', name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
final class CrmParameters
{
}
