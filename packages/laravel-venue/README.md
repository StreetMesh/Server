<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://protocol.streetmesh.com/brand/dark/svg/streetmesh-mark-dark.svg">
  <img alt="StreetMesh" src="https://protocol.streetmesh.com/brand/svg/streetmesh-mark.svg" width="96">
</picture>

# Laravel Venue

**The visitor-facing half of a StreetMesh server: somewhere people arrive, and
the things they can do once they have.**

> Developed in [`Server`](https://github.com/StreetMesh/Server), at
> `packages/laravel-venue`, and published here. Issues and pull requests belong
> there.

A venue is somewhere people go, and almost nobody who goes there lives there.
That is the whole difficulty: a visitor arrives holding an identity issued
somewhere else, and this has to let them in, seat them, keep track of what they
are doing, and write down what happened — without ever holding their account.

## What it provides

| | |
| --- | --- |
| **The door** | Somebody arrives with an address, agrees to what this venue asks for, and comes back holding a delegation. `src/Http/`, and `resources/views/livewire/connect.blade.php` |
| **Experiences** | What there is to do here. An installed package registers one; a visitor sees a menu. `src/Experiences/` |
| **Gatherings** | Who is at which table, which seat, and what became of it. `src/Gatherings/` |
| **Tickets** | A short-lived, signed assertion that a person may sit in a seat in a room, which is the only thing the hub will accept. `src/Realtime/` |
| **Settling** | Asking the hub how something ended and getting a signed record into each participant's own store. `src/Gatherings/Settling.php` |
| **Chat and parties** | Talking to whoever is here, and peer-to-peer audio and video between people who chose each other. `src/Chat/`, `src/Parties/`, `src/Media/` |
| **The hub build** | Collecting the rooms of every installed experience into one runnable Node project. `src/Hub/`, and `php artisan hub:build` |

## What an experience gets from this

An experience implements `StreetMesh\Venue\Experiences\Experience` — a name, a
screen, the scopes it needs, and where its room lives. If it has an outcome
worth keeping, it also implements `StreetMesh\Venue\Experiences\Settles`.

Everything hard is already answered by then: arriving from another server, being
seated, a room with an authoritative referee, a signed record at the end.
[`Laravel-Chess`](https://github.com/StreetMesh/Laravel-Chess) is the worked
example, and it is mostly rules and screens because of it.

`scopes()` is **declared rather than configured**. A venue whose configuration
and installed packages disagreed would walk somebody through a consent screen
and then fail to write the record it had just promised them.

## What it deliberately does not do

**It does not hold anybody's account.** A visitor's identity is issued by their
domicile and the venue only ever holds a delegation — revocable, expiring, and
refused the moment it is withdrawn.

**It is not authoritative over the present moment.** That belongs to
[`Hub`](https://github.com/StreetMesh/Hub), which decides what is true right now
and refuses a browser that says otherwise. This is authoritative over what
*happened*, which is the half that gets signed.

**It is not a kind of server.** Venue is a capability. The same codebase with a
different switch is a domicile, and with both switches on it is both.

## Where this fits

| Where | What it is |
| --- | --- |
| [**Glossary**](https://github.com/StreetMesh/Protocol/blob/main/GLOSSARY.md) | Every term here in plain words — visitor, delegation, gathering, ticket. Start here if any of the above was unfamiliar. |
| [<code>Protocol</code>](https://github.com/StreetMesh/Protocol) | What StreetMesh is. |
| [<code>Protocol&#8209;Laravel</code>](https://github.com/StreetMesh/Protocol-Laravel) | Identity, permission and records, which this asks for rather than implements. |
| [<code>Laravel&#8209;Domicile</code>](https://github.com/StreetMesh/Laravel-Domicile) | The other half: somewhere people live. |
| [<code>Hub</code>](https://github.com/StreetMesh/Hub) | The rooms this hands tickets to. |
| [<code>Server</code>](https://github.com/StreetMesh/Server) | Where to start if you want to run one, and where this is developed. |

## License

MIT.
