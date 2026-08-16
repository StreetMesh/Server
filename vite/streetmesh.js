import { readFileSync, writeFileSync, existsSync, mkdirSync, realpathSync } from 'node:fs'
import { dirname, join, relative, resolve } from 'node:path'

/*
 * What the installed packages put on the page.
 *
 * A package used to be found by where it sat: three globs named
 * `packages/*` and anything installed anywhere else was invisible. That is
 * fine while every package is one of ours and arrives as a directory in this
 * repository, and it stops being fine the moment somebody installs an
 * experience from a registry — it lands in `vendor/`, the globs do not reach
 * it, and its components never register and its markup renders unstyled.
 * Nothing errors, which is the expensive kind of wrong.
 *
 * So a package says what it offers instead of being found by where it lives:
 *
 *   "extra": {
 *       "streetmesh": {
 *           "components": "resources/js/alpine.js",
 *           "views": "resources/views",
 *           "entries": ["resources/js/comms/host.js"]
 *       }
 *   }
 *
 * and this reads Composer's own record of what is installed. Same declaration,
 * same result, wherever Composer put it — which is the point.
 *
 * Two files come out, because Vite and Tailwind cannot be handed a list: they
 * read imports and `@source` lines out of real files. They are generated, they
 * are ignored by git, and they are rewritten every time this runs.
 */

const GENERATED_JS = 'resources/js/streetmesh.generated.js'
const GENERATED_CSS = 'resources/css/streetmesh.generated.css'

export default function streetmesh(root = process.cwd()) {
    const packages = installed(root)

    const components = []
    const sources = []
    const entries = []
    const outside = []

    for (const { name, path } of packages) {
        const declared = declaration(name, path)

        if (!declared) {
            continue
        }

        if (!path.startsWith(root + '/')) {
            outside.push(path)
        }

        if (declared.components) {
            components.push({ name, specifier: specifier(root, path, declared.components, name) })
        }

        for (const views of [declared.views].flat().filter(Boolean)) {
            sources.push(located(root, path, views, name))
        }

        for (const entry of [declared.entries].flat().filter(Boolean)) {
            entries.push(relative(root, located(root, path, entry, name)))
        }
    }

    write(join(root, GENERATED_JS), componentModule(components))
    write(join(root, GENERATED_CSS), sourceSheet(sources))

    return { entries, allow: outside }
}

/**
 * Everything Composer has installed, as real paths.
 *
 * `install-path` is written relative to `vendor/composer/`, and for anything
 * mounted as a path repository it points at a symlink: `vendor/streetmesh/*`
 * are links into `packages/*`. The link is followed here rather than passed
 * on, because Tailwind will not follow one — a `@source` naming a symlinked
 * directory scans nothing, finds no classes, and strips every one of them from
 * the build. That is an unstyled page and not an error, and it is the reason
 * the old glob named `packages/` instead of `vendor/`.
 *
 * It also tells us the truth about where a package really lives, which is what
 * decides whether Vite has to be told it may serve from there.
 */
function installed(root) {
    const manifest = join(root, 'vendor/composer/installed.json')

    if (!existsSync(manifest)) {
        throw new Error(
            `[streetmesh] No ${relative(root, manifest)}. Run \`composer install\` before building assets.`,
        )
    }

    const { packages = [] } = JSON.parse(readFileSync(manifest, 'utf8'))

    return packages.map((composerPackage) => {
        const path = resolve(join(root, 'vendor/composer'), composerPackage['install-path'] ?? '')

        return {
            name: composerPackage.name,
            path: existsSync(path) ? realpathSync(path) : path,
        }
    })
}

function declaration(name, path) {
    const own = join(path, 'composer.json')

    // Read from the package rather than from installed.json. Composer copies
    // `extra` in at install time, so a package edited in place — which is every
    // package in this repository — would otherwise be read as it was when it
    // was installed rather than as it is now.
    if (!existsSync(own)) {
        return null
    }

    return JSON.parse(readFileSync(own, 'utf8')).extra?.streetmesh ?? null
}

/**
 * A declared path, checked.
 *
 * A package that names something it does not ship is the failure this whole
 * arrangement exists to stop being silent, so it is loud here instead — at
 * build time, naming the package and what it claimed.
 */
function located(root, path, declared, name) {
    const full = join(path, declared)

    if (!existsSync(full)) {
        throw new Error(
            `[streetmesh] ${name} declares "${declared}" under extra.streetmesh, and there is nothing at ${readable(root, full)}.`,
        )
    }

    return full
}

/**
 * A path as somebody reading the error would write it: relative to this
 * project when it is in it, and absolute when it is somewhere else entirely.
 */
function readable(root, path) {
    return path.startsWith(root + '/') ? relative(root, path) : path
}

/**
 * How to name a file in the generated module.
 *
 * Root-relative for anything inside this project, because that survives being
 * read from anywhere. Absolute for a package developed outside it — a path
 * repository pointing at a sibling checkout — which Vite will serve once the
 * directory is on its allow list.
 */
function specifier(root, path, declared, name) {
    const full = located(root, path, declared, name)

    return full.startsWith(root + '/') ? '/' + relative(root, full) : full
}

function componentModule(components) {
    const imports = components.map(({ specifier }, i) => `import components${i} from '${specifier}'`)

    const entries = components.map(
        ({ name }, i) => `    { package: ${JSON.stringify(name)}, components: components${i} },`,
    )

    return [
        generatedBy(),
        ...imports,
        imports.length ? '' : null,
        'export default [',
        ...entries,
        ']',
        '',
    ]
        .filter((line) => line !== null)
        .join('\n')
}

function sourceSheet(sources) {
    return [
        generatedBy(),
        ...sources.map((path) => `@source '${path}';`),
        '',
    ].join('\n')
}

function generatedBy() {
    return `/* Generated by vite/streetmesh.js from what Composer has installed. Do not edit. */\n`
}

function write(path, contents) {
    mkdirSync(dirname(path), { recursive: true })

    // Rewriting a file Vite is watching restarts the dev server, and this runs
    // on every start. Only write when it would differ.
    if (existsSync(path) && readFileSync(path, 'utf8') === contents) {
        return
    }

    writeFileSync(path, contents)
}
