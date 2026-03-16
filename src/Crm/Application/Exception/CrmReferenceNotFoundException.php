<?php

declare(strict_types=1);

namespace App\Crm\Application\Exception;

use RuntimeException;

final class CrmReferenceNotFoundException extends RuntimeException
{
    public function __construct(public readonly string $field)
    {
        parent::__construct(sprintf('Unknown "%s" in this CRM scope.', $field));
    }
}
