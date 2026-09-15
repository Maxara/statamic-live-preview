<?php

declare(strict_types=1);

namespace Maxara\StatamicLivePreview\Tags;

use Statamic\Tags\Tags;

/**
 * Marks an element as a clickable target in the live preview.
 *
 *     {{ lp_target }}                 data-lp-set="<id of the set>"
 *     {{ lp_target set="{block:id}" }} override when the context does not fit
 *     {{ lp_target :set="lp_set" }}    passing it empty suppresses the output
 *
 * Without parameters the tag reads `id` from the context — inside a replicator loop
 * that is the set id which Replicator::performAugmentation() passes through.
 *
 * Outside the live preview the tag renders nothing.
 */
class Target extends Tags
{
    protected static $handle = 'lp_target';

    public function index(): string
    {
        // The guard is the context supplement on purpose, not request()->isLivePreview():
        // PreviewController::tokenizeAndReturn() sets it for exactly this, and it lets
        // the tag be tested without token plumbing. The listener takes the request macro
        // instead, because it has no context.
        if (! $this->context->value('live_preview')) {
            return '';
        }

        $set = $this->params->has('set')
            ? $this->params->get('set')
            // An explicitly empty set parameter suppresses the output rather than
            // falling back: a shared partial also runs outside the page builder, where
            // `id` would be the entry id. The caller decides whether to annotate.
            : $this->context->value('id');

        if (blank($set)) {
            return '';
        }

        // Leading space, because the tag sits in attribute position.
        // Antlers escapes nothing, so e() belongs here.
        return ' data-lp-set="'.e($set).'"';
    }
}
