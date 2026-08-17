<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://protocol.streetmesh.com/brand/dark/svg/streetmesh-mark-dark.svg">
  <img alt="StreetMesh" src="https://protocol.streetmesh.com/brand/svg/streetmesh-mark.svg" width="96">
</picture>

# Laravel Domicile

**The resident-facing half of a StreetMesh server: somewhere people live.**

> Developed in [`Server`](https://github.com/StreetMesh/Server), at
> `packages/laravel-domicile`, and published here. Issues and pull requests
> belong there.

A domicile is where somebody's identity and records actually sit. It is the
address other servers resolve, the thing that decides whether a venue may write
anything on their behalf, and the place that keeps what a venue signed after the
venue has stopped existing.

## What it provides

| | |
| --- | --- |
| **Residents** | The people who live here, each with an address of their own — a subdomain, resolved by any server that asks. `src/Residents/` |
| **A directory** | Who lives here, and a page for each of them. `resources/views/livewire/directory.blade.php` and `profile.blade.php` |
| **The capability** | What this server announces about itself to strangers, so a venue walking the chain finds somewhere to ask. `src/DomicileCapability.php` |

The parts that make those work — identities, delegated permission, the record
store — are [`Protocol-Laravel`](https://github.com/StreetMesh/Protocol-Laravel).
This is the interface over them.

## What a resident's identity is

Not an account on this server. A resident holds a `did:plc` identifier, which is
the hash of the operation that created it — so it carries no address, and it
survives them renaming themselves or moving to a different server entirely.

That is the claim the whole project rests on, and it is why this is a package
rather than a `users` table: **a person's identity does not belong to the server
they live on.**

## What it deliberately does not do

**It is not a full Personal Data Server.** Merkle Search Trees, CAR files and
the firehose are not here. The record store is built repo-shaped so that adding
them later is additive rather than a rewrite — records addressed by collection
and key, immutable, sorting by time, opaque to the database.

**It is not a kind of server.** Domicile is a capability. The same codebase with
a different switch is a venue, and with both switches on it is both.

## Where this fits

| Where | What it is |
| --- | --- |
| [**Glossary**](https://github.com/StreetMesh/Protocol/blob/main/GLOSSARY.md) | Every term here in plain words — resident, domicile, delegation, record. Start here if any of the above was unfamiliar. |
| [<code>Protocol</code>](https://github.com/StreetMesh/Protocol) | What StreetMesh is. |
| [<code>Protocol&#8209;Laravel</code>](https://github.com/StreetMesh/Protocol-Laravel) | Identity, permission and records, which this is the interface over. |
| [<code>Laravel&#8209;Venue</code>](https://github.com/StreetMesh/Laravel-Venue) | The other half: somewhere people gather. |
| [<code>Server</code>](https://github.com/StreetMesh/Server) | Where to start if you want to run one, and where this is developed. |

## License

MIT.
