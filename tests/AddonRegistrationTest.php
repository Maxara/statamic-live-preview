<?php

declare(strict_types=1);

namespace SchmidtMax\StatamicLivePreview\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use SchmidtMax\StatamicLivePreview\Listeners\InjectBridge;
use SchmidtMax\StatamicLivePreview\ServiceProvider;
use SchmidtMax\StatamicLivePreview\Tags\Target;
use Statamic\Events\ResponseCreated;
use Statamic\Statamic;

/**
 * Proves the wiring an addon only ever gets wrong in the consuming project: manifest
 * discovery, tag and listener registration, the live-preview input, the Vite build
 * directory and the publish tags.
 */
class AddonRegistrationTest extends TestCase
{
    private const PACKAGE = 'statamic-live-preview';

    public function test_it_registers_the_lp_target_tag(): void
    {
        $this->assertSame(Target::class, app('statamic.tags')['lp_target']);
    }

    public function test_it_listens_for_the_response_created_event(): void
    {
        $listeners = array_map(
            fn ($listener) => is_string($listener) ? $listener : $listener::class,
            Event::getRawListeners()[ResponseCreated::class] ?? [],
        );

        $this->assertContains(InjectBridge::class, $listeners);
    }

    public function test_it_adds_the_bridge_component_to_the_live_preview_inputs(): void
    {
        $this->assertSame(
            'live-preview-bridge',
            config('statamic.live_preview.inputs.preview_bridge'),
        );
    }

    /**
     * The build directory is what InjectBridge reads back out of the registry to find
     * bridge.js. If registerVite() ever derives it differently, this breaks here rather
     * than silently in a preview.
     */
    public function test_it_registers_its_vite_build_under_the_package_name(): void
    {
        $vites = Statamic::availableVites(request());

        $this->assertArrayHasKey(self::PACKAGE, $vites);
        $this->assertSame('vendor/'.self::PACKAGE.'/build', $vites[self::PACKAGE]['buildDirectory']);
        $this->assertNotNull($vites[self::PACKAGE]['hotFile']);
    }

    /**
     * Only the control-panel entry may be handed to the CP layout. bridge.js lives in
     * the same build, but loading it in the control panel would be wrong.
     */
    public function test_it_does_not_hand_the_front_end_bridge_to_the_control_panel(): void
    {
        $input = Statamic::availableVites(request())[self::PACKAGE]['input'];

        $this->assertSame(['resources/js/cp.js'], $input);
    }

    public function test_the_built_manifest_contains_both_entry_points(): void
    {
        $manifest = json_decode(
            file_get_contents(__DIR__.'/../resources/dist/build/manifest.json'),
            true,
        );

        $this->assertArrayHasKey('resources/js/cp.js', $manifest);
        $this->assertArrayHasKey('resources/js/bridge.js', $manifest);
    }

    public function test_it_offers_the_claude_skill_under_its_own_publish_tag(): void
    {
        $paths = LaravelServiceProvider::pathsToPublish(
            ServiceProvider::class,
            'statamic-live-preview-skill',
        );

        $this->assertNotEmpty($paths, 'The skill publish tag is not registered.');
        $this->assertStringEndsWith('.claude/skills/live-preview', reset($paths));
    }

    /**
     * The skill must not hang off the addon slug: bootPublishAfterInstall() publishes
     * that tag automatically on every composer install, which would write into the
     * consuming project's .claude/ directory unasked.
     */
    public function test_the_skill_is_not_published_automatically_on_install(): void
    {
        $autoPublished = LaravelServiceProvider::pathsToPublish(
            ServiceProvider::class,
            self::PACKAGE,
        );

        foreach ($autoPublished as $target) {
            $this->assertStringNotContainsString('.claude', $target);
        }
    }
}
