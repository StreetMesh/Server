<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://protocol.streetmesh.com/brand/dark/svg/streetmesh-mark-dark.svg">
  <img alt="StreetMesh" src="https://protocol.streetmesh.com/brand/svg/streetmesh-mark.svg" width="96">
</picture>

# StreetMesh Server

**Run a place on the open web where people arrive as themselves.**

Somebody signs in to your server with an address issued by a server you have
never heard of, plays a game of chess against somebody from a third, and walks
away holding a signed record of it that still verifies after your server is
gone. That is what this runs.

|  |  |
| --- | --- |
| **What** | A Laravel application that is a **domicile** (somewhere people live), a **venue** (somewhere people gather), or both. Which one is a setting, not a fork. |
| **Who** | Anyone who wants to run a server. Anyone who wants to build something people can do at one — start with [`EXPERIENCES.md`](EXPERIENCES.md). |
| **Where** | Live now: a domicile at [stme.sh](https://stme.sh), a venue at [tabletop.streetmesh.com](https://tabletop.streetmesh.com). One repository, one commit, two deployments. |
| **Why** | Because a person's identity should not belong to the server they live on. Move, rename yourself, or watch a venue disappear — what you were given still verifies. |
| **How** | `did:plc` identities, delegated permission a visitor can revoke, records signed by a venue and written into the visitor's own store. A Node hub is authoritative over the present moment; the venue is authoritative over what happened. |
| **When** | v0 is nearly done. What is left is the part it exists to prove — a game between two people on genuinely different servers. See the [roadmap](https://github.com/StreetMesh/Protocol/blob/main/ROADMAP.md). |

New to the vocabulary? The
[glossary](https://github.com/StreetMesh/Protocol/blob/main/GLOSSARY.md) defines
every term in plain words and says which are ours and which are borrowed.

---

## Quickstart

Requires PHP 8.3, Node 22, and a web server pointing at `public/`.
[Herd](https://herd.laravel.com) is what this is developed against.

```bash
git clone git@github.com:StreetMesh/Server.git
cd Server
composer setup
```

`composer setup` installs everything, writes a `.env`, generates a key,
migrates, and builds the front end.

Then tell it the name strangers will reach it by. Under `did:web` this becomes
the server's own identifier, so it has to be the real one:

```dotenv
STREETMESH_HOST=your.domain
```

A server offers whatever it has installed. Say more only to run two different
servers from one codebase:

```dotenv
STREETMESH_VENUE=false      # somewhere people live, and nothing else
STREETMESH_DOMICILE=false   # somewhere people gather, and nothing else
```

A venue needs somewhere to gather and a secret to recognise it by, and refuses
to start without both:

```dotenv
STREETMESH_HUB=wss://your.hub
STREETMESH_REALTIME_SECRET=   # the same value wherever the hub runs
```

### Running it

Three processes, and **none of them starts the others**. The usual cause of
"it does nothing" is that one is missing.

```bash
./hub-serve     # the rooms      → wss://hub.test
npm run dev     # the assets     → :5173
                # the PHP is Herd, always on
```

### Checking it from outside

```bash
php artisan streetmesh:check
```

```
  identity did:web:server.test
  handle   server.test

  ✓ the name resolves to this identity
  ✓ the document is reachable and claims the name
  ✓ a stranger finds the key we signed with
  ✓ and can verify what we signed

  A stranger can verify what this server signs.
```

This signs something and then comes back over the network as an ordinary client
to verify it, so what it tests is the deployment — DNS, TLS, routing, and
whether the host you configured is the one strangers actually reach. Everything
else can be green while this fails, and the first sign would otherwise be
another server rejecting a record.

For putting one in the cloud, see [`DEPLOYING.md`](DEPLOYING.md).

---

## Contributing

### Where the code is

Everything is in this repository. The host application is deliberately thin —
close to a stock Laravel install — and the behaviour lives in packages:

| Package | Provides |
| --- | --- |
| [`packages/protocol`](packages/protocol) | The protocol in framework-free PHP. Bytes in, bytes out. |
| [`packages/protocol-laravel`](packages/protocol-laravel) | The same, bound to the framework: identity, records, attestations, commits |
| [`packages/laravel-domicile`](packages/laravel-domicile) | The resident-facing half |
| [`packages/laravel-venue`](packages/laravel-venue) | The visitor-facing half |
| [`packages/laravel-chess`](packages/laravel-chess) | An experience, and the worked example of one |
| [`hub/`](hub) | The authoritative multiplayer host. Not PHP. |

Composer mounts `packages/*` as a path repository, so `vendor/streetmesh/*` are
symlinks into them: **an edit is live on the next request**, and one commit
ships a change and the code that uses it.

Each package resolves its siblings the same way, so a package's own suite tests
the code next to it rather than a copy fetched at some earlier date.

### Running the checks

```bash
composer test                                  # the host
cd packages/laravel-venue && composer test     # one package
cd hub && npm run types:check                  # the hub
```

Each package declares its own — lint, static analysis, phpunit, or some of
those — and CI runs exactly what you just ran, as one job per package, so a
failure names the package rather than the repository.

### What a package puts on the page

A package declares its browser assets in its own `composer.json`, and
[`vite/streetmesh.js`](vite/streetmesh.js) resolves them against Composer's
record of what is installed:

```json
"extra": {
    "streetmesh": {
        "components": "resources/js/alpine.js",
        "views": "resources/views",
        "entries": ["resources/js/comms/host.js"]
    }
}
```

`components` is merged into Alpine, `views` is scanned by Tailwind, `entries`
is built as an entry point. Because this reads what Composer installed rather
than a path, an experience installed into `vendor/` works exactly as one in
`packages/` does. **Declaring a path you do not ship fails the build**, naming
the package and the claim.

Styles are the exception to "live immediately": a class the build has never
seen needs `npm run build` before it looks right — an unstyled element rather
than an error.

### Publishing

Packagist reads one package per repository, at the root of it, so each package
is released to a repository of its own:

```bash
composer split -- --dry-run    # what it would publish, and where
composer split                 # publish
```

Where each goes is read from `support.source` in its `composer.json`, and from
`repository` in the hub's `package.json`. It refuses a dirty tree, refuses to
publish commits this repository has not pushed, and never forces.

Nothing is on Packagist or npm yet, so there is currently no way to install any
of this except from here.

---

## License

The host application is MIT. Each package declares its own — all five are MIT —
with the text at `packages/<name>/LICENSE`. StreetMesh prose and documentation
at the org level are CC BY-NC-SA 4.0.
