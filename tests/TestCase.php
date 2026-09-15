<?php

declare(strict_types=1);

namespace Seitwerk\StatamicLivePreview\Tests;

use Seitwerk\StatamicLivePreview\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
