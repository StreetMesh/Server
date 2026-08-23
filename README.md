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

```bash
composer create-project streetmesh/server my-server
```

It asks one question — whether this is somewhere people live, somewhere they
gather, or both — and writes the answers, along with a name for the server and
a secret for its hub.

```
? What is this server?
  › A domicile — people live here, and their records are yours to keep
    A venue — people arrive from elsewhere and do things together
    Both — one server, two halves
```

The name matters more than the rest and is the one thing to get right first.
It is this server's identifier, every signature is checked against it, and it
is read when the first identity is minted — so changing it afterwards renames
nothing that already exists. Use the real one, not a local alias.

Then run it:

```bash
php artisan serve  # or Herd, or whatever you use
npm run dev        # watch the assets
./hub-serve        # the Node half, if this is a venue
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

```bash
php artisan streetmesh:experience acme/laravel-bingo
```

That writes one that installs, registers, appears on the menu and passes its
own tests — and does nothing, which is where you start.
[`Chess2D`](https://github.com/StreetMesh/Chess2D) is the worked example of
where it goes next.

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

This repository is the application — the part you own after
`composer create-project`. Everything that makes it a StreetMesh server is
installed, and is developed elsewhere.

| | |
| --- | --- |
| [`StreetMesh/Laravel`](https://github.com/StreetMesh/Laravel) | `streetmesh/laravel` — what turns this into a StreetMesh server, and where the Node hub lives |
| [`StreetMesh/Protocol-PHP`](https://github.com/StreetMesh/Protocol-PHP) | `streetmesh/protocol` — the protocol in framework-free PHP |
| [`StreetMesh/Chess2D`](https://github.com/StreetMesh/Chess2D) | `streetmesh/chess-2d` — an experience, and the example of one |
| [`StreetMesh/Protocol`](https://github.com/StreetMesh/Protocol) | What StreetMesh is: guides, decisions, conformance vectors |

Working on the library alongside this application is one line:

```bash
composer config repositories.laravel path ../Laravel
```

## License

The application is MIT, and so is every package. StreetMesh prose and
documentation at the organization level are CC BY-NC-SA 4.0.
