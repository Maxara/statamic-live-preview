<?php

declare(strict_types=1);

namespace Seitwerk\StatamicLivePreview;

use Seitwerk\StatamicLivePreview\Listeners\InjectBridge;
use Seitwerk\StatamicLivePreview\Tags\Target;
use Statamic\Events\ResponseCreated;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    /**
     * Only the control-panel entry is listed here.
     *
     * vite.config.js builds bridge.js as a second entry point on purpose: it belongs
     * to the previewed page, not to the control panel, and InjectBridge resolves it
     * from the very same manifest. Listing it here would load it in the CP too.
     */
    protected $vite = [
        'input' => [
            'resources/js/cp.js',
        ],
        'publicDirectory' => 'resources/dist',
        'hotFile' => __DIR__.'/../resources/dist/hot',
    ];

    protected $tags = [
        Target::class,
    ];

    protected $listen = [
        ResponseCreated::class => [
            InjectBridge::class,
        ],
    ];

    public function bootAddon(): void
    {
        $this->bootPreviewInput()->bootSkill();
    }

    /**
     * Render the bridge component into the live-preview header bar.
     *
     * JavascriptComposer reads config('statamic.live_preview') per request at view
     * compose time, long after booting. config()->set() in boot() therefore applies
     * under config:cache as well — caching skips loading the files, not the booting.
     */
    private function bootPreviewInput(): self
    {
        config()->set('statamic.live_preview.inputs', array_merge(
            config('statamic.live_preview.inputs', []),
            ['preview_bridge' => 'live-preview-bridge'],
        ));

        return $this;
    }

    /**
     * Offer the Claude skill that teaches {{ lp_target }} to whoever writes blocks.
     *
     * Deliberately its own tag rather than the addon slug: under the slug,
     * bootPublishAfterInstall() would write into the consuming project's .claude/
     * directory on every composer install. An addon has no business touching someone
     * else's tooling config unasked, so this stays opt-in.
     *
     * Laravel's publishes() rather than $publishables, because the latter hardwires
     * its destination to public_path() and cannot reach .claude/ at all.
     */
    private function bootSkill(): self
    {
        $this->publishes([
            __DIR__.'/../resources/skills/live-preview' => base_path('.claude/skills/live-preview'),
        ], 'statamic-live-preview-skill');

        return $this;
    }
}
