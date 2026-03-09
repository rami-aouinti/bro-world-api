<?php

declare(strict_types=1);

namespace App\Page\Transport\AutoMapper\Contact;

use App\General\Transport\AutoMapper\RestAutoMapperConfiguration;
use App\Page\Application\DTO\Contact\ContactCreate;
use App\Page\Application\DTO\Contact\ContactPatch;
use App\Page\Application\DTO\Contact\ContactUpdate;

class AutoMapperConfiguration extends RestAutoMapperConfiguration
{
    protected static array $requestMapperClasses = [
        ContactCreate::class,
        ContactUpdate::class,
        ContactPatch::class,
    ];

    public function __construct(RequestMapper $requestMapper)
    {
        parent::__construct($requestMapper);
    }
}
