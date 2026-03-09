<?php

declare(strict_types=1);

namespace App\Page\Transport\AutoMapper\Contact;

use App\General\Transport\AutoMapper\RestRequestMapper;

class RequestMapper extends RestRequestMapper
{
    protected static array $properties = ['languageId', 'content'];
}
