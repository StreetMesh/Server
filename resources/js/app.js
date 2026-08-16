import './echo.js'

/*
 * What the browser gets, beyond what Livewire and Flux bring themselves.
 *
 * Nothing here names a package. An installed package that puts something on the
 * page ships a module exporting its components by name and points at it from
 * its own `composer.json`:
 *
 *   "extra": { "streetmesh": { "components": "resources/js/alpine.js" } }
 *
 * The generated module is that declaration, resolved — see vite/streetmesh.js.
 * It is real imports rather than a runtime lookup, so a component that is not
 * in the bundle is a build failure rather than a page that quietly does less
 * than it should.
 */

import packages from './streetmesh.generated.js'

document.addEventListener('alpine:init', () => {
    for (const { package: name, components } of packages) {
        if (!components) {
            console.warn(`[streetmesh] ${name} declares components and its module exports none, so nothing from it reaches the page.`)

            continue
        }

        for (const [component, definition] of Object.entries(components)) {
            window.Alpine.data(component, definition)
        }
    }
})
