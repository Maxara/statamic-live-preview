import PreviewBridge from './PreviewBridge.vue';

Statamic.booting(() => {
    Statamic.$components.register('live-preview-bridge', PreviewBridge);
});
