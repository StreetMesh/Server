# StreetMesh Server

**Where to start if you want to run a StreetMesh server** — a domicile, a venue,
or one server that is both.

This is the starting point, not a worked example. It is close to a stock Laravel
install and stays that way: **all StreetMesh capability arrives as Laravel
packages mounted under [`packages/`](packages/)**, so that each capability stays
portable into any other Laravel host and keeps its own history, issues and
release cadence. What is left here is the glue that cannot live in a package,
and it should stay small enough to read in one sitting.

For servers you can look at rather than build from, see
[`Home`](https://github.com/StreetMesh/Home) — what a dedicated domicile can
look like — and [`Games`](https://github.com/StreetMesh/Games), the same for a
venue. Both are this repository with capabilities installed and configured, and
both actually run.

A domicile and a venue are separate applications in separate checkouts, as
anybody operating them would have it. A server that is both is a matter of
configuration, not of sharing a directory.

**Building something people can do at a venue?** Start at
[`EXPERIENCES.md`](EXPERIENCES.md) instead — the four processes a working server
needs running, the shape of an experience package, and the traps that cost the
most time.

For the bigger picture — the values, the vocabulary, and how this fits into Avatars / Protocol / MeshObject / StreetTiles / Hub / Browser — see **The Dream** at <https://github.com/StreetMesh>.

## Stack

- PHP `^8.3`
- Laravel `^13.0` (Framework 13.7.x)
- SQLite by default (swap via `.env`)

## Getting a server running

The packages are git submodules, and Composer will not tell you if they are
missing — so clone recursively:

```bash
git clone --recurse-submodules git@github.com:StreetMesh/Server.git
cd Server
composer setup
```

`composer setup` inits the submodules first and then does the rest. If you
already cloned without `--recurse-submodules`, this is the missing step:

```bash
git submodule update --init --recursive
```

Or the long way, which is what `setup` runs:

```bash
git submodule update --init --recursive
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

Capability arrives as Composer packages rather than as code here.

| Package | Repo | Provides |
| --- | --- | --- |
| `streetmesh/protocol-laravel` | [`Protocol-Laravel`](https://github.com/StreetMesh/Protocol-Laravel) | Identity, records, attestations, commits |
| `streetmesh/laravel-domicile` | [`Laravel-Domicile`](https://github.com/StreetMesh/Laravel-Domicile) | The resident-facing half |
| `streetmesh/laravel-venue` | [`Laravel-Venue`](https://github.com/StreetMesh/Laravel-Venue) | The visitor-facing half |

And one that is not a Composer package, because it is not PHP:

| Where | Repo | Provides |
| --- | --- | --- |
| `hub/` | [`Hub`](https://github.com/StreetMesh/Hub) | The authoritative multiplayer host. Rooms, and who is allowed in them. |

`hub/` is a submodule like the others, and needs `npm install` inside it before
it will run or check anything. It is the only part of a server that is not PHP,
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

Both live in [`routes/web.php`](routes/web.php) and
[`resources/views/streetmesh`](resources/views/streetmesh), because they belong
to the application rather than to any package. Two routes sharing a path do not
collide loudly in Laravel — the later silently replaces the earlier — so a
package claiming the root would win or lose on boot order with nobody deciding.

## How the packages are wired in

Two mechanisms stacked, and it is worth knowing they are two.

**The source is a git submodule, not a Composer download.** Each package under
`packages/` is a submodule of its own repository — see [`.gitmodules`](.gitmodules).

**Composer mounts them as a path repository**, one glob covering all of them:

```json
"repositories": [{ "type": "path", "url": "packages/*" }]
```

So `vendor/streetmesh/*` are symlinks into `packages/*`, and the version
constraints in `require` are `"*"` — no constraint is doing any work, because
the submodule pointer is what decides the version.

What follows from that:

- A fresh clone or worktree needs `git submodule update --init --recursive`.
  `git worktree remove` needs `--force`.
- **A deploy has to init the submodules before installing.** On Laravel Cloud,
  the build command must start with `git submodule update --init --recursive`.

  Skip it and **nothing complains**. Composer prints `Symlinking from
  packages/protocol` and exits `0` — but the directories are empty, so
  `vendor/streetmesh/` is never created and the failure surfaces later as a
  missing class at runtime. There is no install-time error to notice, which is
  the whole reason this is written down.
- Editing a package is live immediately — no `composer update`, because the
  symlink is the working copy. That is the point of the arrangement.
- Shipping a package change is two commits: one in the package repository, then
  one here moving the submodule pointer.

```bash
cd packages/<name>
git checkout main
# ...edit, commit, push to StreetMesh/<Package>...
cd ../..
git add packages/<name>
git commit -m "Bump <name> to <sha>"
```

Forget the second commit and you get the half-shipped symptom: it works on your
machine and the deploy is still on the old pointer.

### Styles are the exception to "live immediately"

Tailwind scans for class names at build time and will not look inside a package
on its own, so [`resources/css/app.css`](resources/css/app.css) names them:

```css
@source '../../packages/*/resources/views/**/*.blade.php';
```

Named at `packages/` rather than at `vendor/`, because `vendor/streetmesh/*` are
symlinks and a scanner that declines to follow one would silently find nothing.

A package change that introduces a class the build has never seen needs
`npm run build` before it looks right. The PHP side of the change is live and the
styling is not — an unstyled element rather than an error.

### Adding a new package

```bash
git submodule add https://github.com/StreetMesh/<Package>.git packages/<slug>
composer require streetmesh/<slug>:*
```

No `composer config repositories...` step — the `packages/*` glob already covers
it. The package's `composer.json` should declare its service provider under
`extra.laravel.providers` so Laravel's package discovery wires it up
automatically.

## License

The host application code in this repository is MIT-licensed. Individual packages declare their own licenses inside `packages/<name>/LICENSE`. The StreetMesh prose and documentation at the org level are CC BY-NC-SA 4.0.
