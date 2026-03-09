<?php

declare(strict_types=1);

namespace App\Page\Transport\AutoMapper\Faq;

use App\General\Transport\AutoMapper\RestAutoMapperConfiguration;
use App\Page\Application\DTO\Faq\FaqCreate;
use App\Page\Application\DTO\Faq\FaqPatch;
use App\Page\Application\DTO\Faq\FaqUpdate;

class AutoMapperConfiguration extends RestAutoMapperConfiguration
{
    protected static array $requestMapperClasses = [
        FaqCreate::class,
        FaqUpdate::class,
        FaqPatch::class,
    ];

    public function __construct(RequestMapper $requestMapper)
    {
        parent::__construct($requestMapper);
    }
}
