<?php
declare(strict_types=1);

namespace Increazy\ApiExtenderV2\Model;

interface WebClientInterface 
{
    public function initialize(string $entity, string $event);

    public function pushWebhook(array $payload);
}


