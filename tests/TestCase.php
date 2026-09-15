<?php

declare(strict_types=1);

namespace SchmidtMax\StatamicLivePreview\Tests;

use SchmidtMax\StatamicLivePreview\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
