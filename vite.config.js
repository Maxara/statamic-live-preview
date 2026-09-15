import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import statamic from '@statamic/cms/vite-plugin';

// Two entry points, one build. cp.js is what the control panel loads (declared as
// $vite input in the ServiceProvider); bridge.js belongs to the previewed page and is
// resolved from this same manifest by the InjectBridge listener.
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/cp.js',
                'resources/js/bridge.js',
            ],
            publicDirectory: 'resources/dist',
        }),
        statamic(),
    ],
});
