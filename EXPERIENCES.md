# Building a Venue Experience

A guide to getting a StreetMesh server running on your own machine and adding
something to it.

Written for whoever arrives next — a person or a model — and biased heavily
toward the things that cost hours the first time. Most of the traps at the end
are ones this project actually walked into, and every one of them failed in a
way that pointed somewhere other than the cause.

---

## What an experience is

Something people do at a venue: a game, a shop, a screening room. It is a
**Composer package** that a venue operator installs, and it usually spans three
surfaces.

| Surface | Where it lives | What it is for |
|---|---|---|
| **Rules** | `room/src/room.ts` | The authority on what may happen. Runs in the hub, not the browser. |
| **Screen** | `resources/views/livewire/` + `resources/js/` | What a visitor sees and touches. |
| **Records** | `src/` | What outlives the session — written into each participant's own repository. |

The division matters more than it looks. The hub decides whether a move is
legal, because a browser is somebody else's computer. The hub cannot sign
anything and forgets everything when it stops, so the *venue* is what makes a
result durable — and the record ends up in the players' own stores rather than
in the venue's.

An experience is **not** a capability. A capability answers "what kind of server
is this" and is announced in a DID document to strangers. An experience answers
"what can I do here", which is nobody's business until they have arrived.

---

## The moving parts

Three things run. **None of them starts the others**, and the usual cause of "it
does nothing" is that one is missing.

| | Command | Serves | Symptom when it is down |
|---|---|---|---|
| **PHP** | Herd (always on) | `https://server.test` | Site does not load |
| **Assets** | `npm run dev` | `https://server.test:5173` | Page renders unstyled; no interactivity |
| **Hub** | `./hub-serve` | `wss://hub.test` → `:2567` | Boards connect to nothing; "Could not reach the table" |

```sh
cd ~/repos/StreetMesh/Server

./hub-serve        # terminal 1 — builds this server's hub, then runs it
npm run dev        # terminal 2 — Vite
```

There used to be a fourth, and getting rid of it is worth a sentence: the PLC
directory ran in Docker, on its own host, with a Postgres beside it — a
container, a compose file and a daemon to remember, for the sake of four
endpoints. It is now a setting:

```
STREETMESH_PLC_HOST=true
STREETMESH_PLC_DIRECTORY="${APP_URL}/plc"
```

### Why each exists

**The hub** is authoritative shared state. One hub per venue, not per
experience: an operator installs several experiences and the hub serves all
their rooms together.

**The directory** is where `did:plc` identities live. A resident's identifier is
the hash of the operation that created it, so it carries no address and survives
them renaming or moving. In development this server keeps its own, at `/plc`;
in production point `STREETMESH_PLC_DIRECTORY` at the real one and leave
`STREETMESH_PLC_HOST` off.

> **The local directory will not recover an identity.** The real one lets a
> higher-priority rotation key fork a chain and nullify what a lower one did,
> which is how somebody takes an identity back from a server that has gone bad.
> Ours refuses the conflict instead. That is the right trade for development and
> the wrong one for a registry anybody relies on.

> **And it is our reading of the spec rather than the spec's own software.**
> That distinction has already cost us once: running [Bluesky's
> implementation](https://github.com/did-method-plc/did-method-plc) caught a
> defect our own tests had agreed with for months. `./plc-serve` still stands it
> up in Docker, and `php packages/protocol/bin/check-plc.php https://plc.test`
> still checks us against it — worth doing when anything about operations,
> signatures or CIDs changes, and not worth doing daily.

---

## First run

```sh
git clone https://github.com/StreetMesh/Server.git
cd Server

composer install
npm install

cp .env.example .env
php artisan key:generate     # identities are encrypted at rest; without this the server holds none
php artisan migrate
```

### Configuration that matters

| Key | Local value | What breaks without it |
|---|---|---|
| `APP_URL` | `https://server.test` | Links and redirects point at the wrong host |
| `APP_KEY` | `php artisan key:generate` | The server can hold no identity at all — keys are encrypted at rest |
| `STREETMESH_HOST` | `server.test` | The DID document publishes `https://` with no host, and every venue walking the chain finds nothing |
| `STREETMESH_HUB` | `wss://hub.test` | A server that says it is a venue refuses to start — nothing it offers could open |
| `STREETMESH_REALTIME_SECRET` | any string | A venue refuses to start; without it a hub cannot tell it anything |
| `STREETMESH_VENUE` / `STREETMESH_DOMICILE` | unset | Nothing. Unset means offered, which is what one machine running one of each wants. Set either to `false` and that capability is not offered at all — no screens, nothing in the navigation, nothing in the DID document |

---

## Anatomy of an experience

Chess is the worked example. Copy its shape.

```
packages/laravel-chess/
├── composer.json                     # name, provider, path repositories
├── src/
│   ├── ChessServiceProvider.php      # registers the experience
│   ├── ChessExperience.php           # implements Experience
│   └── Games.php                     # opening, joining, writing records
├── routes/web.php                    # its own screens
├── resources/
│   ├── views/livewire/               # Livewire single-file components
│   │   ├── lobby.blade.php
│   │   └── table.blade.php
│   └── js/
│       ├── alpine.js                 # exports the browser components
│       └── table.js
├── room/
│   ├── package.json
│   └── src/room.ts                   # the rules
└── tests/
```

### The one name, used three times

```php
'com.streetmesh.games.chess'
```

An NSID names the **collection** records go in, the **room type** the hub
serves, and the **experience** itself. One name because they are one thing seen
from three sides. Reverse-domain, so two authors cannot collide by accident.

### `Experience`

```php
interface Experience
{
    public function name(): string;         // the NSID
    public function title(): string;        // on the menu
    public function description(): string;  // one sentence
    public function icon(): string;         // a Flux icon name
    public function route(): string;        // its own screen
    public function action(): ?string;      // button text, or null for "Launch"
    public function scopes(): array;        // what a visitor must agree to
    public function room(): ?string;        // where its room lives, or null
}
```

`scopes()` is **declared, not configured**. A venue whose configuration and
installed packages disagreed would walk somebody through a consent screen and
then fail to write the record it had just promised them.

`room()` returns an absolute path to the directory holding your room, or `null`
if your experience has nothing live — a gallery is a perfectly good experience
with nobody to keep in step. It is declared for the same reason: the server
copies what you point at into the hub it builds, rather than guessing at a
layout that belongs to your package.

### `room/src/room.ts`

```ts
import { Occupant, VenueRoom, type Ticket } from '@streetmesh/hub'

export class ChessRoom extends VenueRoom<ChessState> { /* ... */ }

export default { name: 'com.streetmesh.games.chess', room: ChessRoom }
```

The default export shape is the contract. `VenueRoom` handles ticket
verification; you implement the rules.

### `resources/js/alpine.js`

```js
import chessTable from './table.js'

export default { chessTable }
```

Exported **by name**, not registered. A package that reaches for a global
`window.Alpine` only works in one application.

Point at it from your `composer.json`, which is what makes the server look:

```json
"extra": { "streetmesh": { "components": "resources/js/alpine.js" } }
```

---

## How installing is one step

Nothing has a registry. Your package says what it offers and the server finds
it through Composer's own record of what is installed, which is why installing
an experience needs no wiring on the server's side at all — and why it works
the same whether your package sits in `packages/` or arrives from a registry
into `vendor/`.

| What | How it is found | Where you declare it |
|---|---|---|
| PHP | `extra.laravel.providers` | `composer.json` |
| Screens | `Livewire::addNamespace(...)` | your provider |
| Browser JS | `extra.streetmesh.components` | `composer.json` |
| Styles | `extra.streetmesh.views` | `composer.json` |
| Entry points | `extra.streetmesh.entries` | `composer.json` |
| Hub rooms | `Experience::room()`, collected by `php artisan hub:build` | your `Experience` |

So the whole of the front-end declaration is:

```json
"extra": {
    "laravel": { "providers": ["Acme\\Hello\\HelloServiceProvider"] },
    "streetmesh": {
        "components": "resources/js/alpine.js",
        "views": "resources/views"
    }
}
```

`components` is a module exporting your Alpine components by name. `views` is
scanned by Tailwind, so every class in your markup survives the build. `entries`
is for anything that has to be built as an entry point rather than imported —
rare, and the venue's comms widget is the example.

**Declaring something you do not ship is a build failure**, naming your package
and what it claimed. That is deliberate: the alternative was a component that
never registered and markup that rendered unstyled, with nothing said either
way.

The declarations are read when Vite starts, not in the browser. If a newly
installed package's components do not appear, **restart Vite** before looking
anywhere else.

### npm dependencies are the exception

The PHP half installs itself. The JavaScript half does not: your package's
browser and hub code resolve imports from the **host's** `node_modules`, so
anything you import has to be in the host's `package.json`. Chess relies on the
host having `colyseus.js`, `chess.js` and `@colyseus/schema` for exactly this
reason.

Prefer inlining small assets over adding a dependency. Chess carries its piece
artwork as path data rather than importing an icon package — a few hundred bytes
against one more thing an operator has to install, and one more registry that
can refuse to serve it.

---

## Working on packages

`packages/*` live in this repository, mounted by a Composer path repository:

```json
"repositories": [{ "type": "path", "url": "packages/*" }]
```

Edit them **in `Server/packages/`**. `vendor/streetmesh/*` are symlinks into
that directory, so a change is live on the next request and one commit ships
both halves of it.

Each package resolves its siblings the same way — `packages/laravel-chess/vendor/
streetmesh/laravel-venue` is a symlink to `packages/laravel-venue` — so a
package's own suite tests the code next to it rather than a copy fetched at some
earlier date.

---

## Checking it works

Prefer these to reasoning about it. Nearly every real defect in this project was
found by running something.

```sh
php packages/protocol/bin/check-plc.php https://plc.test   # mint, rename, move an identity
php bin/check-visit.php                                    # the whole delegation dance
php bin/check-permission.php
php bin/mint-ticket.php                                    # a ticket the hub will take
cd hub && npm run check:ticket && npm run check:join
```

And per package: `composer test` (pint, phpstan, phpunit).

---

## Traps

Ordered by how much time each one costs. **Symptom first**, because that is what
you will have.

### Two people share a party, a seat, or a name

Not a bug in whichever of those you noticed. Two visitors are holding the same
identity, and everything that keys on one is faithfully treating them as one
person.

It happens when somebody arrives at the door as one handle and signs in to their
own server as another — an autofilled login form is enough, and no screen
disagrees afterwards, because a venue displays the handle it was given and acts
on the identity it was handed.

A venue refuses this now, naming both. If you meet it on an older checkout, look
at the delegations rather than at whatever surfaced it:

```sh
php artisan tinker --execute="\StreetMesh\Protocol\Laravel\Permissions\Delegation::get(['id','did','handle'])->each(fn(\$d) => print(\"{\$d->id} {\$d->did} {\$d->handle}\n\"));"
```

Two rows with different handles and the same identifier is the whole story.

### Every ticket is refused, for reasons that have nothing to do with tickets
The hub was started directly with `node`. Node does **not** read the macOS
keychain, so fetching the venue's DID document over TLS fails. `./hub-serve`
exports `NODE_EXTRA_CA_CERTS`. **Never start the hub any other way.**

### `room name "com" not defined`
Colyseus room types are URL path segments and **cannot contain dots**. The hub
maps `com.streetmesh.games.chess` → `com_streetmesh_games_chess`. Watch for this
in any refusal test: a broken door can show four green ticks while every refusal
passes for the wrong reason.

### A Livewire component in a package is not found
`loadViewsFrom()` registers a **Blade** namespace. Livewire keeps a separate
register and consults only what `Livewire::addNamespace()` gave it. You need
both.

### Text from the middle of a script is on the page, in a corner, on every screen

A Blade directive named in a comment. Blade is a text preprocessor and knows
nothing about JavaScript, so `@livewireScripts` inside a `/* … */` is compiled
like any other: Livewire's script tags are injected into the comment, the first
of them closes the surrounding `<script>`, and everything after it renders as
text. Write `@@livewireScripts` to mean the word.

`assertSee` will not catch it — the text is in the raw source either way. Assert
that the literal directive survived into the output, which only happens when
Blade left it alone.

### Half a form vanishes, and every test still passes
Nested double quotes in a Blade attribute — `:description="__("don't")"` —
make Blade give up on the tag, emit it as literal text, and swallow everything
after it. `assertSee()` passes because it matches the raw source. Escape with
`\'`, and assert `assertDontSee('<flux:', escape: false)`.

### A session value quietly destroys another
Session keys are dot-notation paths. Writing `streetmesh.visitor.intended`
**replaces** whatever `streetmesh.visitor` held. Keep keys as siblings, never as
one another's children.

### One person is sitting in two chairs

A delegation is one trip through the door, not a person. Coming back — the next
day, or in another browser — mints a fresh one for the same human, so anything
keyed on `delegation_id` counts them twice. Ask by `did`.

### A config default never applies
`config('a.b', $default)` returns the default only when the key is **absent**. A
key that is present and `null` — the ordinary case for an unset env var — gives
you `null`. Use `config('a.b') ?? $default`.

### `npm install` fails with "must provide string spec"
`npm pkg set dependencies.colyseus.js=…` treats the dot as a path separator and
writes `"colyseus": {"js": …}`. Edit `package.json` by hand for dotted package
names.

### Signatures verify locally and are refused by other servers
ECDSA `(r, s)` has an equally valid twin `(r, n − s)`. ATProtocol requires the
lower one. OpenSSL picks at random **and verifies both**, so round-trip tests
pass on signatures the network rejects. `P256::sign()` normalizes now — if you
add signing anywhere, keep it low-S.

### The board says "Could not reach the table" in Safari and not in Chrome

`wss://`, not `ws://`. The page is served over https, and a browser refuses an
insecure WebSocket from a secure page — Chrome makes an exception for localhost
and Safari does not, so this looks like a Safari bug and is not one. The hub sits
behind Herd's TLS at `hub.test`:

```sh
herd proxy hub http://127.0.0.1:2567 --secure
```

### A resident's name resolves to the server
Resident handles are subdomains (`alice.server.test`), so `.well-known`
documents must be answered **per hostname**. `*.test` resolves locally through
dnsmasq, so this works on your machine without configuration.

---

## Deliberately not solved yet

- **Settling** — nothing yet asks the hub for a finished game's result.
- **Audio/video** — WebRTC peer-to-peer first, LiveKit at v1. Signalling goes
  through the hub.
- **Text chat** — Laravel events over Reverb.
- **Separate deployments** — domicile and venue on one server first.
