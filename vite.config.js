import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',

                /*
                 * The comms widget. It runs in this document and stays there:
                 * the badge, panel and stage are iframes it arranges and draws
                 * into, but the camera, the microphone and the peer connections
                 * are held here, because a navigation reloads every frame on
                 * the page and a stream cannot outlive the document that
                 * acquired it.
                 *
                 * Named here because it is an entry point rather than something
                 * imported, which is the one thing the package cannot arrange
                 * for itself.
                 */
                'packages/laravel-venue/resources/js/comms/host.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
