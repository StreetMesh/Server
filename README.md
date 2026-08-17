<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://protocol.streetmesh.com/brand/dark/svg/streetmesh-mark-dark.svg">
  <img alt="StreetMesh" src="https://protocol.streetmesh.com/brand/svg/streetmesh-mark.svg" width="96">
</picture>

# StreetMesh Server

**A place on the spatial web, and the software that runs one.**

Somebody arrives at your venue with an address issued by a server you have
never heard of. They sit down at a table, play a game of chess against somebody
from a third server, and talk to them while they play — voice, video, the
ordinary things people do while they play a game together.

When the game ends, each of them walks away holding a signed record of it. Not
a row in your database. A record kept on the server *they* live on, in a store
they control, that still verifies after your venue has shut down and after you
have rotated every key you signed it with.

That is the whole of it, and every part has to be real for any of it to work.

## People live somewhere

A **domicile** is where somebody's identity and their records actually sit. It
is the address other servers resolve, and it is the thing that decides whether
a venue may write anything on their behalf.

What it is *not* is an account. A resident holds an identifier that is the hash
of the operation that created it — so it carries no address at all, and it
survives them renaming themselves, or moving to a different server, or falling
out with you. **A person's identity does not belong to the server they live
on.** Everything else here follows from that one sentence.

## People gather somewhere

A **venue** is somewhere people go, and almost nobody who goes there lives
there. That is the whole difficulty and the whole point: a visitor arrives
holding an identity issued elsewhere, and your venue has to let them in, seat
them, keep track of what they are doing, and write down what happened —
without ever holding their account.

They agree, once, at the door, to exactly what your venue asks for. They can
take it back whenever they like, and when they do, your venue is refused.

## A server is not a kind of thing

Domicile and venue are **capabilities**, not species. The same code deployed
twice with different settings is one of each. Nothing is forked and nothing is
copied — which is the claim being tested, not a convenience.

Two of them are running right now, from this repository, on this commit: a
domicile at [stme.sh](https://stme.sh) and a venue at
[tabletop.streetmesh.com](https://tabletop.streetmesh.com).

**Want to build something people can do at a venue?** A game, a shop, a
screening room. Start at [`EXPERIENCES.md`](EXPERIENCES.md).

**Unfamiliar words?** The
[glossary](https://github.com/StreetMesh/Protocol/blob/main/GLOSSARY.md) has
all of them in plain language, and says which are ours and which we borrowed.
[The Dream](https://github.com/StreetMesh) is where the whole idea starts.

---

## Quickstart

You need PHP 8.3, Node 22, and a web server pointing at `public/`.
[Herd](https://herd.laravel.com) is what this is developed against.

```bash
git clone git@github.com:StreetMesh/Server.git
cd Server
composer setup
```

That installs everything, writes a `.env`, generates a key, migrates, and
builds the front end.

Now tell it the name strangers will reach it by. This becomes the server's own
identifier, so it has to be the real one and not a local alias:

```dotenv
STREETMESH_HOST=your.domain
```

A server offers whatever it has installed, which is all most servers need to
say. Say more only to run two different servers from one codebase:

```dotenv
STREETMESH_VENUE=false      # somewhere people live, and nothing else
STREETMESH_DOMICILE=false   # somewhere people gather, and nothing else
```

A venue needs somewhere to gather and a secret to recognise it by, and will not
start without both:

```dotenv
STREETMESH_HUB=wss://your.hub
STREETMESH_REALTIME_SECRET=   # the same value wherever the hub runs
```

### Three things run

**None of them starts the others**, and the usual cause of "it does nothing" is
that one is missing.

```bash
./hub-serve     # the rooms      → wss://hub.test
npm run dev     # the assets     → :5173
                # the PHP is Herd, always on
```

### Ask a stranger whether it worked

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

This signs something, then comes back over the open network as an ordinary
client and checks it — so what it tests is your deployment: DNS, TLS, routing,
and whether the host you configured is the one people can actually reach.
Everything else can be green while this fails, and the first sign would
otherwise be another server quietly rejecting your records.

Putting one in the cloud is [`DEPLOYING.md`](DEPLOYING.md).

---

## Contributing

### Where the code lives

All of it is here. The application is deliberately thin — close to a stock
Laravel install — and the behaviour is in packages:

| Package | What it is |
| --- | --- |
| [`packages/protocol`](packages/protocol) | The protocol in framework-free PHP. Bytes in, bytes out. |
| [`packages/protocol-laravel`](packages/protocol-laravel) | The same, bound to the framework: identity, records, attestations, commits |
| [`packages/laravel-domicile`](packages/laravel-domicile) | Somewhere people live |
| [`packages/laravel-venue`](packages/laravel-venue) | Somewhere people gather |
| [`packages/laravel-chess`](packages/laravel-chess) | An experience, and the worked example of one |
| [`hub/`](hub) | Authoritative over the present moment. Not PHP. |

`vendor/streetmesh/*` are symlinks into `packages/*`, so **an edit is live on
the next request** and one commit ships a change together with the code that
uses it. Each package resolves its siblings the same way, so a package's suite
tests the code beside it rather than a copy fetched some time ago.

### Running the checks

```bash
composer test                                  # the application
cd packages/laravel-venue && composer test     # one package
cd hub && npm run types:check                  # the hub
```

CI runs exactly these, one job per package, so a failure names the package
rather than the repository.

### What a package puts on the page

A package says so in its own `composer.json`, and
[`vite/streetmesh.js`](vite/streetmesh.js) resolves it against Composer's
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

Because that reads what Composer installed rather than a path, somebody else's
experience in `vendor/` works exactly as ours in `packages/` do. **Declaring a
path you do not ship fails the build**, and says which package claimed what.

Styles are the one exception to "live immediately": a class the build has never
seen needs `npm run build` first — an unstyled element rather than an error.

### Publishing

Packagist reads one package per repository, at the root of it, so each is
released to a repository of its own:

```bash
composer split -- --dry-run    # what it would publish, and where
composer split                 # publish
```

It refuses a dirty tree, refuses to publish commits this repository has not
pushed, and never forces. Nothing is on Packagist or npm yet, so for now there
is no way to install any of this except from here.

---

## License

The application is MIT, and so is every package, with the text at
`packages/<name>/LICENSE`. StreetMesh prose and documentation at the
organization level are CC BY-NC-SA 4.0.
