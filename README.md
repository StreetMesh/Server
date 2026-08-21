<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://protocol.streetmesh.com/brand/dark/svg/streetmesh-mark-dark.svg">
  <img alt="StreetMesh" src="https://protocol.streetmesh.com/brand/svg/streetmesh-mark.svg" width="96">
</picture>

# StreetMesh Server

**Design and deploy multiplayer experiences on a server you control — where people sign in with an identity that belongs to them, not you.**

StreetMesh Servers are Laravel applications supported by Node processes. Set one up and you will have created somewhere people can visit through multiple modalities — mobile, XR, and IRL — and do things together: game, shop, learn, and generally be entertained.

## What is StreetMesh?

[StreetMesh](https://streetmesh.com) is a collection of MIT-licensed open source software projects aimed squarely at an important problem: How do we keep the Internet open, federated, and decentralized as it transitions (slowly but surely) from hyperlinks to the [metaverse](https://en.wikipedia.org/wiki/Metaverse)?

## What's does a StreetMesh Server do?

- **Hosts portable identities.** StreetMesh users rent or own *domiciles* in other StreetMesh servers; the *address* of their domicile, like `username.stme.sh` or even `me.mydomain.com`, functions as their username, like in [AT Protocol](https://atproto.com/articles/atproto-ethos#identity-based-authority), and their server stores their data
- **Hosts multiplayer venues.** StreetMesh servers can be *domiciles* or they can host *experiences*, or they can do both; when a server hosts experiences, it is called a *venue*
- **Orchestrates modular experiences.** StreetMesh experiences are distributed as [Composer](https://getcomposer.org) packages: create your own, mix and match others', curate the perfect venue for your audience
- **Syncs and adjudicates experience state.** Powered by [Colyseus](https://colyseus.io), running on Node: your server's *Hub* enforces the rules of the experiences you host, and syncs the data the players share
- **Coordinates text, audio, and video chat.** Your server can facilitate peer-to-peer connections for up to four players in *parties*; experiences host spatialized audio chat using [LiveKit](https://livekit.com/)

## Requirements

To run a server, you need PHP 8.3, Node 22, and a web server,
usually nginx running php-fpm. Running the Laravel server app locally for dev work, we like [Herd](https://herd.laravel.com). In the cloud, we like [Laravel Cloud](https://cloud.laravel.com). For the Node processes, you'll
just run a node script locally. In the cloud, you can use [Colyseus Cloud](https://colyseus.io/cloud-managed-hosting/). We'll be adding features to this project soon that allow you to start a Colyseus server in Laravel Forge on your preferred hosting platform (AWS, DigitalOcean, etc.).

## Getting Started

Checkout the Server project and run setup:

```bash
git clone git@github.com:StreetMesh/Server.git
cd Server
composer setup
```

The composer script installs everything, writes an `.env` file, generates keys, migrates your database scripts, and builds the front end.

Next, give the server an address. In StreetMesh, the server's address is also its identifier, so use the real one rather than a local alias:

```dotenv
STREETMESH_HOST=your.domain
```

A server offers whatever it has installed:

```dotenv
# set at least one of these to true
STREETMESH_VENUE=false      # a place people live, and nothing else
STREETMESH_DOMICILE=false   # a place people gather, and nothing else
```

If you're running a venue server, you're going to need a *Hub*, which is
the part of the architecture that runs on Node. The node processes and
your Laravel server authenticate using a shared secret. When you run

```dotenv
STREETMESH_HUB=wss://your.hub
STREETMESH_REALTIME_SECRET=   # the same value wherever the hub runs
```

Run everything for development:

```bash
./hub-serve        # the hub, if you're running a venue
php artisan serve  # use Herd, the laravel local server, or whatever
npm run dev        # monitor your assets for changes and rebuild
```

Test the stack:

```bash
> php artisan streetmesh:check
  ✓ the name resolves to this identity
  ✓ the document is reachable and claims the name
  ✓ a stranger finds the key we signed with
  ✓ and can verify what we signed
```

Ready to put one on the internet? [`DEPLOYING.md`](DEPLOYING.md) covers the
venue, the hub, and the order they have to be released in.

## Build something people can do here

An experience is a Composer package: a game, a shop, a gallery. It ships its
own screens, its own rules, and decides what is worth writing down afterwards.
Chess is the worked example — copy its shape.

**[EXPERIENCES.md](EXPERIENCES.md)** is the guide, and it leads with the traps,
because every one of them cost this project hours.

## Learning StreetMesh

The [Protocol](https://protocol.streetmesh.com) repository is the
authority on what StreetMesh is: guides, decisions, and the conformance vectors.

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

## License

The application is MIT, and so is every package. StreetMesh prose and
documentation at the organization level are CC BY-NC-SA 4.0.
