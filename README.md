# StreetMesh Server

Reference implementation of a **StreetMesh Server** — the Laravel host that StreetMesh Browsers connect to in order to render Places, voice NPCs, run Scenes, and bridge into the StreetMesh Protocol.

This repository is intentionally thin. The server itself is close to a stock Laravel install; **all StreetMesh capability is delivered as Laravel packages mounted as git submodules** under [`packages/`](packages/). That keeps each capability portable into other Laravel hosts and gives every package its own history, issues, and release cadence.

For the bigger picture — the values, the vocabulary, and how this fits into Avatars / Protocol / MeshObject / StreetTiles / Hub / Browser — see **The Dream** at <https://github.com/StreetMesh>.

## Stack

- PHP `^8.3`
- Laravel `^13.0` (Framework 13.7.x)
- SQLite by default (swap via `.env`)

## Packages

| Path | Repo | Status |
| --- | --- | --- |
| — | — | None mounted yet |

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
