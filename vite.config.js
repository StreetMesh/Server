import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from "@tailwindcss/vite";
import streetmesh from './vite/streetmesh.js';

/*
 * What the installed packages contribute, read from Composer's record of what
 * is installed rather than from a glob over `packages/*`. A package installed
 * from a registry lands in `vendor/` and is reached by exactly the same
 * declaration — which is what lets somebody else's experience work here.
 *
 * `entries` are the files a package needs built as entry points rather than
 * imported: the venue's comms widget is one, because it holds the camera, the
 * microphone and the peer connections in this document, where a navigation
 * cannot reload them out from under a stream.
 *
 * `allow` is anything installed from outside this directory — a path
 * repository pointing at a checkout somebody is developing beside us. Vite
 * refuses to serve a file outside its root until told, and the refusal reads
 * like a missing file.
 */
const packages = streetmesh(import.meta.dirname);

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',
                ...packages.entries,
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
        fs: {
            allow: ['.', ...packages.allow],
        },
    },
});
