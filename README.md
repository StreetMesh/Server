<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://protocol.streetmesh.com/brand/dark/svg/streetmesh-mark-dark.svg">
  <img alt="StreetMesh" src="https://protocol.streetmesh.com/brand/svg/streetmesh-mark.svg" width="96">
</picture>

# StreetMesh Server

**Design and deploy multiplayer experiences on a server you manage — where people sign in with an identity that belongs to them, not you.**

StreetMesh Server is a Laravel application. Install it and you have somewhere
people can visit and do things together: games, shops, screening rooms. They
arrive with a username issued by a server you have never heard of, and you
never hold their account.

## What you get

- **Portable identity.** Visitors arrive from their preferred StreetMesh servers, called *domiciles*; their data and identity belongs to their server, not yours.
- **Authoritative multiplayer rooms.** Powered by [Colyseus](https://colyseus.io), running on Node: your server's *Hub* enforces the rules of the experiences you host, and syncs the data the players share.
- **Voice and video.** Peer-to-peer parties between visitors, with no service in the middle.
- **Text chat**, venue-wide, over Laravel Reverb.
- **Experiences as Composer packages.** Install one, and it brings its own screens, rules and records with it.

## Get it running

You need PHP 8.3, Node 22, and a web server pointing at `public/`.
[Herd](https://herd.laravel.com) is what this is developed against.

```bash
git clone git@github.com:StreetMesh/Server.git
cd Server
composer setup
```

That installs everything, writes a `.env`, generates a key, migrates, and
builds the front end.

Tell it the name strangers will reach it by. This becomes the server's own
identifier, so use the real one rather than a local alias:

```dotenv
STREETMESH_HOST=your.domain
```

A venue needs somewhere to gather and a secret to recognise it by, and will not
start without both:

```dotenv
STREETMESH_HUB=wss://your.hub
STREETMESH_REALTIME_SECRET=   # the same value wherever the hub runs
```

A server offers whatever it has installed:

```dotenv
STREETMESH_VENUE=false      # a place people live, and nothing else
STREETMESH_DOMICILE=false   # a place people gather, and nothing else
```

### Three processes

**None of them starts the others**, and the usual cause of "nothing happens" is
that one is missing.

```bash
./hub-serve     # the rooms      → wss://hub.test
npm run dev     # the assets     → :5173
                # the PHP is Herd, always on
```

Now open the venue, sign in, and play a game of chess against yourself in a
second browser. You have just exercised the whole stack — federated sign-in, an
authoritative room, and a signed record at the end of the game.

### Check it from the outside

```bash
php artisan streetmesh:check
```

```
  ✓ the name resolves to this identity
  ✓ the document is reachable and claims the name
  ✓ a stranger finds the key we signed with
  ✓ and can verify what we signed
```

This signs something, then comes back over the network as an ordinary client
and verifies it — so it tests your deployment, not your code. DNS, TLS,
routing, and whether the host you configured is the one people can actually
reach. Everything else can pass while this fails, and the first sign would
otherwise be another server rejecting your records.

Ready to put one on the internet? [`DEPLOYING.md`](DEPLOYING.md) covers the
venue, the hub, and the order they have to be released in.

## Build something people can do here

An experience is a Composer package: a game, a shop, a gallery. It ships its
own screens, its own rules, and decides what is worth writing down afterwards.
Chess is the worked example — copy its shape.

**[EXPERIENCES.md](EXPERIENCES.md)** is the guide, and it leads with the traps,
because every one of them cost this project hours.

## Where things stand

v0 is a game of chess played between two people on different servers, each
ending up with their own verifiable record of it. Everything above works today.
What is left is the part it exists to prove: that game played across the open
network rather than between two identities on one machine.

Not yet in v0, each for a stated reason: a full Personal Data Server, messaging
between domiciles, commerce, and a spatial interface. The
[roadmap](https://github.com/StreetMesh/Protocol/blob/main/ROADMAP.md) says why.

## Learning StreetMesh

The [Protocol](https://github.com/StreetMesh/Protocol) repository is the
authority on what StreetMesh is: guides, decisions, and the conformance vectors
that settle any argument about the wire. It publishes at
[protocol.streetmesh.com](https://protocol.streetmesh.com).

The [glossary](https://github.com/StreetMesh/Protocol/blob/main/GLOSSARY.md)
defines every term in plain language and says which are ours and which we
borrowed. [The Dream](https://github.com/StreetMesh) is where the whole idea
starts.

## Contributing

Everything is in this repository — the application, five Composer packages, and
the Node hub.

| | |
| --- | --- |
| [`packages/protocol`](packages/protocol) | The protocol in framework-free PHP |
| [`packages/protocol-laravel`](packages/protocol-laravel) | The same, bound to the framework |
| [`packages/laravel-domicile`](packages/laravel-domicile) | A place people live |
| [`packages/laravel-venue`](packages/laravel-venue) | A place people gather |
| [`packages/laravel-chess`](packages/laravel-chess) | An experience, and the example of one |
| [`hub/`](hub) | The multiplayer host. Not PHP. |

`vendor/streetmesh/*` are symlinks into `packages/*`, so an edit is live on the
next request and one commit ships a change with the code that uses it.

```bash
composer test                                  # the application
cd packages/laravel-venue && composer test     # one package
cd hub && npm run types:check                  # the hub
```

CI runs exactly these, one job per package, so a failure names the package
rather than the repository. Publishing is `composer split`, which releases each
package to a repository of its own — nothing is on Packagist or npm yet, so for
now the only way to get this is from here.

## License

The application is MIT, and so is every package. StreetMesh prose and
documentation at the organization level are CC BY-NC-SA 4.0.
