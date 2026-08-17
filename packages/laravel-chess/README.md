<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://protocol.streetmesh.com/brand/dark/svg/streetmesh-mark-dark.svg">
  <img alt="StreetMesh" src="https://protocol.streetmesh.com/brand/svg/streetmesh-mark.svg" width="96">
</picture>

# Laravel Chess

**Chess as a StreetMesh venue experience — and the first thing to use the
framework rather than be it.**

Two people who live on different servers play a game, and each of them ends up
holding their own verifiable record of it. That sentence is the whole of v0, and
this package is the part of it that is actually chess.

> Developed in [`Server`](https://github.com/StreetMesh/Server), at
> `packages/laravel-chess`, and published here. Issues and pull requests belong
> there.

## What an experience turns out to be

Everything hard is asked for rather than built. Arriving from another server,
being seated, a room with an authoritative referee, a signed record at the end —
all of that belongs to [`Laravel-Venue`](https://github.com/StreetMesh/Laravel-Venue),
[`Protocol-Laravel`](https://github.com/StreetMesh/Protocol-Laravel) and
[`Hub`](https://github.com/StreetMesh/Hub).

What is left is three things:

| | |
| --- | --- |
| `room/src/room.ts` | The rules, enforced where a browser cannot argue with them. |
| `resources/views/livewire/` | The screens: a lobby, and a board. |
| `src/Games.php` | What a finished game is worth writing down. |

**The rules exist once.** They are in the hub and nowhere else — the board draws
what the room reports and asks for what somebody clicked, and PHP never learns
how a knight moves. Two implementations of the rules would be two chances to
disagree about who won.

## The record

Each player gets their own, signed by the venue, written into the store they
chose to live on. Separate records rather than one shared one, because there is
no shared place for one to live: the players are on different servers, and after
tonight this venue may not exist.

Written from each player's point of view — `result: win` rather than
`winner: white` — because it is their record of a thing that happened to them
rather than a row in somebody's database. It carries the moves, the final
position, and how it ended, since "checkmate" and "the other player left" are
different stories about the same result.

A player whose server refuses is skipped rather than failing the other. Somebody
having withdrawn permission is an ordinary answer, and their opponent should
still get their record.

## Where this fits

| Where | What it is |
| --- | --- |
| [**Glossary**](https://github.com/StreetMesh/Protocol/blob/main/GLOSSARY.md) | Every term here in plain words — venue, ticket, attestation, gathering. Start here if any of the above was unfamiliar. |
| [<code>Protocol</code>](https://github.com/StreetMesh/Protocol) | What StreetMesh is. |
| [<code>Laravel&#8209;Venue</code>](https://github.com/StreetMesh/Laravel-Venue) | The venue this sits on: the door, and who is at which table. |
| [<code>Hub</code>](https://github.com/StreetMesh/Hub) | The rooms this puts rules into. |
| [<code>Server</code>](https://github.com/StreetMesh/Server) | Where to start if you want to run one. |

## License

MIT.
