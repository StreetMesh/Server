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

For the bigger picture — the values, the vocabulary, and how this fits into Avatars / Protocol / MeshObject / StreetTiles / Hub / Browser — see **The Dream** at <https://github.com/StreetMesh>.

## Stack

- PHP `^8.3`
- Laravel `^13.0` (Framework 13.7.x)
- SQLite by default (swap via `.env`)

## Getting a server running

```bash
composer install
cp .env.example .env
php artisan key:generate          # required — identity keys are encrypted at rest
php artisan migrate
```

Then tell it the name strangers will reach it by. Under `did:web` this decides
the server's own identifier, so it has to be the real one rather than a local
alias:

```dotenv
STREETMESH_HOST=your.domain
STREETMESH_VENUE=false           # true if this server hosts experiences
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

### Running both halves at once

A server may be a domicile, a venue, or both — they are capabilities rather than
kinds of server. Installing both works, and the one rule that makes it work is
that **no package claims the front page**.

Two routes sharing a method and path do not collide loudly in Laravel: the later
one silently replaces the earlier, and nothing says so. A package taking `/`
would therefore win or lose on boot order, and nobody would have decided it. So
capabilities mount under their own names and the root belongs here, in
[`routes/web.php`](routes/web.php), where the application decides:

```dotenv
STREETMESH_HOME=domicile         # where the front door leads
STREETMESH_MOUNT_DOMICILE=domicile
STREETMESH_MOUNT_VENUE=venue
```

Each half then renders navigation collected from whatever is installed, so a
venue appearing beside a domicile shows up in its menu without either package
knowing the other exists. The DID document is built from the same list, so what
a server tells strangers and what it shows people cannot drift apart.

Each package is a git submodule **and** a Composer package consumed via a `path` repository declared in the host [`composer.json`](composer.json), so edits inside `packages/<name>/` are picked up immediately without re-installing.

[`StreetMesh/StoryEngine`](https://github.com/StreetMesh/StoryEngine) was mounted here and has been unmounted for now. It comes back when there is a resident AI to talk about; until then this server is about the protocol underneath one. Its own repository is unaffected, and `git log -- packages/story-engine` has the wiring if it is wanted again.

## Getting started

```bash
git clone --recurse-submodules git@github.com:StreetMesh/Server.git
cd Server
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

If you cloned without `--recurse-submodules`:

```bash
git submodule update --init --recursive
```

## Working on a package

Submodules track their own `main`. To work on a package:

```bash
cd packages/<name>
git checkout main
# ...edit, commit, push to StreetMesh/<Package>...
cd ../..
git add packages/<name>
git commit -m "Bump <name> to <sha>"
```

## Adding a new package submodule

```bash
git submodule add git@github.com:StreetMesh/<Package>.git packages/<package-slug>
composer config repositories.<package-slug> path packages/<package-slug>
composer require streetmesh/<package-slug>:@dev
```

The package's `composer.json` should declare its service provider under `extra.laravel.providers` so Laravel's package discovery wires it up automatically.

## License

The host application code in this repository is MIT-licensed. Individual packages declare their own licenses inside `packages/<name>/LICENSE`. The StreetMesh prose and documentation at the org level are CC BY-NC-SA 4.0.
