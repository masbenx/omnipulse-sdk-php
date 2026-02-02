<?php

namespace OmniPulse\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony Bundle for OmniPulse
 * 
 * Add to config/bundles.php:
 * OmniPulse\Symfony\OmniPulseBundle::class => ['all' => true],
 */
class OmniPulseBundle extends Bundle
{
    public function getPath(): string
    {
        return dirname(__DIR__, 2);
    }
}
