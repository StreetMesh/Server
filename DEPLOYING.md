# Deploying a StreetMesh server

A server is one thing to an operator and two things to deploy: the PHP
application, and — if it is a venue — the hub its experiences run in.

This describes the arrangement this project uses, which is Laravel Cloud for the
application and Colyseus Cloud for the hub. Neither is required by the protocol.
The hub is a plain Node process and the application is a plain Laravel one.

---

## The shape of it

```
your machine                     git                    the cloud
────────────                     ───                    ─────────
edit a room
./hub-serve  ──rebuilds──▶ hub-build/  ──push──▶  Laravel Cloud  (the venue)
                                             └─▶  Colyseus Cloud (the hub)
```

**`hub-build/` is committed.** It is generated, and it is checked in anyway,
because the alternative does not exist: Colyseus Cloud's build container has
Node and nothing else — no PHP, no Composer, no submodules — and the list of
installed experiences lives in a PHP registry. The translation from "what this
server has installed" to "a runnable Node project" has to happen where PHP is,
and git is what carries the result.

**You do not have to remember to build it.** `./hub-serve` runs
`php artisan hub:build` before it starts, and the hub you test against *is* the
artifact that deploys. Changing a room without regenerating means you never ran
it.

---

## The hub, on Colyseus Cloud

Once per server. Create a Space and an Application, then:

**Build settings**

| Setting | Value |
|---|---|
| Root Directory | `hub-build` |
| Install Command | `npm install` |
| Build Command | `npm run build` |

`npm run build` is `tsc --noEmit`. Nothing is emitted — the sources run as they
are, on Node 22's type stripping — so what it buys is a deploy that fails on a
type error rather than one that starts and falls over.

**Deploy settings.** Two ways in, and they are alternatives:

- **A GitHub connection** deploys on every push to the chosen branch. Simple,
  but every push moves the hub, and a hub restart disposes every room and ends
  every game in progress.
- **A deploy key and a CLI token** put you in control of when. This is what
  `php artisan hub:deploy` uses.

With the CLI, add the **Public SSH deploy key** to the repository (read-only is
enough: Colyseus only reads). Note that **GitHub allows a deploy key on exactly
one repository** — moving an application from one repo to another means deleting
the key from the old one first.

Then take the **Deploy CLI Token**, and get the application id by running the
interactive flow once from the repository root:

```sh
npx @colyseus/cloud deploy
```

It opens a browser, you pick the application, and it writes
`.colyseus-cloud.json`. **That file holds a credential and is gitignored.** Read
the two values out of it and set them as environment variables instead.

> The application id is a **string**, not the number in the dashboard URL —
> `1742-chess`, not `1742`. Guessing it produces
> `Application not found for 'token' provided`.

---

## The venue, on Laravel Cloud

**Build commands** run before traffic moves to the new release, so a hub that
cannot be released fails the venue's release too — which is the ordering worth
having:

```sh
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan hub:deploy
```

**Deploy commands**

```sh
php artisan migrate --force
```

`hub:deploy` rebuilds the artifact, refuses if the checkout is dirty or if what
is committed is not what this server builds, asks the running hub which build it
is, and **does nothing when it already matches**. That last part is what keeps a
copy change to a Blade file from ending somebody's game.

> **Unverified:** whether Laravel Cloud's build container has a `.git` directory
> with remotes. The Colyseus CLI needs one — it works out which commit to deploy
> from git rather than uploading anything. If it does not, run the CLI from
> GitHub Actions on changes to `hub-build/**` instead, and drop `hub:deploy`
> from the build commands.

**Submodules.** `packages/*` are submodules, and `composer.json` resolves them
as path repositories. Without them, `composer install` finds empty directories
and nothing works.

### Environment

```dotenv
STREETMESH_HOST=tabletop.example                 # the real name, not an alias
STREETMESH_VENUE=true
STREETMESH_DOMICILE=false
STREETMESH_HUB=wss://your-app.colyseus.cloud
STREETMESH_REALTIME_SECRET=                      # the same value on the hub

COLYSEUS_APPLICATION_ID=
COLYSEUS_TOKEN=
```

A venue refuses to start without a hub and a secret. That is deliberate: the
failure without either is silence — results never arrive, nothing errors, and
the venue looks perfectly well.

Also needed: **Reverb**, for presence, and a **queue worker**. `Settling` is
queued, and without a worker a finished game never reaches anybody's repository.

The hub needs `STREETMESH_REALTIME_SECRET` set to the same value, in the
Colyseus application's own environment. It is a comma-separated list, newest
first, which is how it rotates: add the new one, deploy both, remove the old.

---

## A domicile

Simpler: no hub, no secret, no experiences.

```dotenv
STREETMESH_HOST=example.org
STREETMESH_DOMICILE=true
STREETMESH_VENUE=false
STREETMESH_PLC_DIRECTORY=https://plc.directory
```

`STREETMESH_VENUE=false` is what makes "no hub" a configuration rather than a
crash — the venue package is installed either way, because both kinds of server
are built from one codebase.

**Every resident's handle is a subdomain**, and each one serves
`/.well-known/atproto-did` over TLS. So the application needs both `example.org`
and `*.example.org`, and a wildcard certificate — which on Laravel Cloud means
an `_acme-challenge` **CNAME delegation** that has to stay in place permanently.
A wildcard A record alone does not get you a certificate.

> Entries in `https://plc.directory` are **permanent and global**. Point this at
> a directory of your own until the domain is real. The protocol refuses to
> publish a `.test` or `.local` handle to the public one, which is a guard
> against exactly one afternoon's mistake and not a substitute for meaning it.
