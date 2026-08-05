import './echo.js'

/*
 * What the browser gets, beyond what Livewire and Flux bring themselves.
 *
 * Nothing here names a package. An installed package that puts something on the
 * page ships `resources/js/alpine.js` exporting its components by name, and
 * this finds it — the same arrangement as styles, found by a `@source` glob,
 * and screens, which resolve through a Livewire namespace.
 *
 * `import.meta.glob` is Vite's, and it resolves at build time rather than in
 * the browser: the pattern becomes real imports in the bundle, so nothing is
 * fetched dynamically and nothing is looked up at runtime.
 */

const packages = import.meta.glob('../../packages/*/resources/js/alpine.js', { eager: true })

document.addEventListener('alpine:init', () => {
    for (const [path, module] of Object.entries(packages)) {
        const components = module.default

        if (!components) {
            console.warn(`[streetmesh] ${path} exports no components, so nothing from it reaches the page.`)

            continue
        }

        for (const [name, component] of Object.entries(components)) {
            window.Alpine.data(name, component)
        }
    }
})
