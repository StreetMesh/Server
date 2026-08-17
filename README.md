# StreetMesh Server

**Where to start if you want to run a StreetMesh server** — a domicile, a venue,
or one server that is both.

This is both the starting point and the worked example. It is close to a stock
Laravel install and stays that way: **all StreetMesh capability arrives as
Laravel packages under [`packages/`](packages/)**, so that each capability stays
portable into any other Laravel host. They are developed here and released from
here — one repository, one commit for a change and the code that uses it. What is
left in the host is the glue that cannot live in a package, and it should stay
small enough to read in one sitting.

**Everything is installed here — domicile, venue, and an experience — and
configuration decides which of them a given deployment actually is.** Turn the
venue off and this is a domicile; turn the domicile off and it is a venue; leave
both on and it is a server that does both. Nothing is forked and nothing is
copied.

That is not a convenience, it is the claim being tested. Two servers built from
one commit, differing only by a switch, is what makes "domicile and venue are
capabilities rather than kinds of server" a fact about the code rather than a
sentence in a glossary.

It is what runs in front of you: a domicile at [`stme.sh`](https://stme.sh) and
a venue at
[`tabletop.streetmesh.com`](https://tabletop.streetmesh.com), from this
repository — see [`DEPLOYING.md`](DEPLOYING.md).

They are separate *applications*, with their own databases, their own identities
and their own domains, as anybody operating them would have it. They are not
separate *codebases*.

**Building something people can do at a venue?** Start at
[`EXPERIENCES.md`](EXPERIENCES.md) instead — the three processes a working server
needs running, the shape of an experience package, and the traps that cost the
most time.

For the bigger picture — the values, the vocabulary, and how this fits into Avatars / Protocol / MeshObject / StreetTiles / Hub / Browser — see **The Dream** at <https://github.com/StreetMesh>.

## Stack

- PHP `^8.3`
- Laravel `^13.17`
- SQLite by default (swap via `.env`)

## Getting a server running

Everything is in this repository, including the packages:

```bash
git clone git@github.com:StreetMesh/Server.git
cd Server
composer setup
```

Or the long way, which is what `setup` runs:

```bash
composer install
cp .env.example .env
php artisan key:generate          # required — identity keys are encrypted at rest
php artisan migrate
npm install && npm run build
```

Then tell it the name strangers will reach it by. Under `did:web` this decides
the server's own identifier, so it has to be the real one rather than a local
alias:

```dotenv
STREETMESH_HOST=your.domain
```

A server offers whatever it has installed, which is all most servers need to
say. Say more only to run two different servers from one codebase — each
capability has a switch named after itself, and setting one to `false` takes it
off this server entirely:

```dotenv
STREETMESH_VENUE=false           # somewhere people live, and nothing else
STREETMESH_DOMICILE=false        # somewhere people gather, and nothing else
```

A server that says it is a venue needs a hub to gather in and a secret to
recognise it by, and refuses to start without either:

```dotenv
STREETMESH_HUB=wss://your.hub
STREETMESH_REALTIME_SECRET=      # the same value wherever the hub runs
```

For putting one in the cloud, see [DEPLOYING.md](DEPLOYING.md).

And check that it worked from outside:

```bash
php artisan streetmesh:check
```

```
  identity did:web:server.test
  handle   server.test
  key      zDnaeeCAJ234321mT1dRMkgq3FpMTmbNVLEMmAZMtCYgpEhXU (p256)

  ✓ the name resolves to this identity        did:web:server.test
  ✓ the document is reachable and claims the name  both directions agree
  ✓ a stranger finds the key we signed with   zDnaeeCAJ234321mT1dR…
  ✓ and can verify what we signed             checked against what it published

  A stranger can verify what this server signs.
```

That check signs something and then goes over the network as an ordinary client
to verify it, so what it exercises is the deployment — DNS, TLS, routing, and
whether the configured host is the one strangers can actually reach. Everything
else can be green while this fails, and the first sign would otherwise be another
server rejecting a record.

## Packages

Capability arrives as Composer packages rather than as code in `app/`. All of
them live here, under [`packages/`](packages/):

| Package | Provides |
| --- | --- |
| `streetmesh/protocol` | The protocol in framework-free PHP. Bytes in, bytes out. |
| `streetmesh/protocol-laravel` | The same, bound to the framework: identity, records, attestations, commits |
| `streetmesh/laravel-domicile` | The resident-facing half |
| `streetmesh/laravel-venue` | The visitor-facing half |
| `streetmesh/laravel-chess` | An experience, and the worked example of one |

And one that is not a Composer package, because it is not PHP:

| Where | Provides |
| --- | --- |
| [`hub/`](hub/) | The authoritative multiplayer host. Rooms, and who is allowed in them. |

Each of these had its own repository until they came in-tree, and those
repositories still exist. **None of them is where the work happens** — see
[below](#they-used-to-be-submodules). Nothing is published to Packagist or npm
yet, so there is currently no way to install any of this except from here.

`hub/` is in this repository like the packages, and needs `npm install` inside it
before it will run or check anything. It is the only part of a server that is not PHP,
and it holds no credential — see its README for why that is the whole of its
security model.

### Two surfaces, and everything else

A server may be a domicile, a venue, or both — capabilities rather than kinds of
server. Almost nothing overlaps when both are installed: a directory of
residents, a menu of experiences, a browser for somebody's own records are
screens with names nothing else wants.

Two surfaces are different, because a server has one of each however many
capabilities it offers.

**The front page** is what anybody sees at the root, signed in or not. One root,
so a server offering more than one capability says which greets people:

```dotenv
STREETMESH_FRONT_PAGE=domicile
```

**The home page** is what somebody signed in sees. It is a collection of panels
offered by whatever is installed, arranged by whoever runs the server:

```php
'home_page' => ['domicile.records', 'venue.experiences'],   // or null for everything
```

A name nothing provides is skipped rather than fatal, so removing a package does
not break a page.

Both live in [`routes/web.php`](routes/web.php) and are drawn by
[`welcome.blade.php`](resources/views/welcome.blade.php) and
[`dashboard.blade.php`](resources/views/dashboard.blade.php), because they belong
to the application rather than to any package. Two routes sharing a path do not
collide loudly in Laravel — the later silently replaces the earlier — so a
package claiming the root would win or lose on boot order with nobody deciding.

## How the packages are wired in

**The source is in this repository.** Each package under `packages/` is ordinary
tracked content, committed here alongside the application that installs it.

**Composer mounts them as a path repository**, one glob covering all of them:

```json
"repositories": [{ "type": "path", "url": "packages/*" }]
```

So `vendor/streetmesh/*` are symlinks into `packages/*`, and the version
constraints in `require` are `"*"` — no constraint is doing any work, because
the checkout decides the version.

What follows from that:

- A clone needs nothing but `composer install`. So does a worktree, and so does
  a deploy.
- Editing a package is live immediately — no `composer update`, because the
  symlink is the working copy. That is the point of the arrangement.
- Shipping a package change is one commit, in the same place as the change to
  the application that uses it.

### They used to be submodules

Until they came in-tree, each package lived in its own repository and was
mounted here as a git submodule. Two things are worth keeping from that.

**The history came with them**, so `git blame` on a package file answers with
the commit that wrote it, under the path it had at the time. `git log` on the
new path does not reach back, because the files moved; the old heads are tagged
so you can get at them anyway:

```bash
git log pre-monorepo/laravel-venue -- src/Chat/Chat.php
```

**The repositories they came from still exist** and still hold that history.
They are no longer where the work happens — a change made there does not reach
this server, and nothing here will tell you so.

### What a package puts on the page

A package declares its browser assets in its own `composer.json`, and
[`vite/streetmesh.js`](vite/streetmesh.js) resolves those declarations against
`vendor/composer/installed.json` — Composer's record of what is installed:

```json
"extra": {
    "streetmesh": {
        "components": "resources/js/alpine.js",
        "views": "resources/views",
        "entries": ["resources/js/comms/host.js"]
    }
}
```

`components` is merged into Alpine, `views` is scanned by Tailwind, `entries` is
built as entry points. Two generated files carry the result to the tools that
need it — Vite reads imports and Tailwind reads `@source` lines, and neither can
be handed a list. Both are gitignored and rewritten whenever Vite starts.

This replaced a pair of globs over `packages/*`. Those found every package we
ship and nothing installed anywhere else, so an experience arriving from a
registry into `vendor/` had its components silently not register and its markup
silently render unstyled. Declared paths are checked, and naming something you
do not ship fails the build instead.

Symlinks are resolved before anything is written, because Tailwind will not
follow one: a `@source` naming `vendor/streetmesh/*` scans nothing and strips
every class it should have found.

### Styles are still the exception to "live immediately"

A package change that introduces a class the build has never seen needs
`npm run build` before it looks right. The PHP side of the change is live and the
styling is not — an unstyled element rather than an error.

### Adding a new package

```bash
mkdir packages/<slug>          # ...with a composer.json naming streetmesh/<slug>
composer require streetmesh/<slug>:*
```

No `composer config repositories...` step — the `packages/*` glob already covers
it. The package's `composer.json` should declare its service provider under
`extra.laravel.providers` so Laravel's package discovery wires it up
automatically.

## License

The host application code in this repository is MIT-licensed. Each package
declares its own license in its `composer.json` — all five are MIT — and carries
the text at `packages/<name>/LICENSE`. The StreetMesh prose and documentation at
the org level are CC BY-NC-SA 4.0.
