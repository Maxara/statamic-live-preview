<?php

declare(strict_types=1);

namespace Maxara\StatamicLivePreview\Tests;

use Maxara\StatamicLivePreview\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
