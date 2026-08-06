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

Four things run. **None of them starts the others**, and the usual cause of "it
does nothing" is that one is missing.

| | Command | Serves | Symptom when it is down |
|---|---|---|---|
| **PHP** | Herd (always on) | `https://server.test` | Site does not load |
| **Assets** | `npm run dev` | `https://server.test:5173` | Page renders unstyled; no interactivity |
| **Hub** | `./hub-serve` | `wss://hub.test` → `:2567` | Boards connect to nothing; "Could not reach the table" |
| **Directory** | `./plc-serve` | `https://plc.test` → `:2582` | Registration fails; identities unresolvable |

```sh
cd ~/repos/StreetMesh/Server

./plc-serve        # terminal 1 — Docker: PLC directory + Postgres
./hub-serve        # terminal 2 — builds this server's hub, then runs it
npm run dev        # terminal 3 — Vite
```

### Why each exists

**The hub** is authoritative shared state. One hub per venue, not per
experience: an operator installs several experiences and the hub serves all
their rooms together.

**The directory** is where `did:plc` identities live. A resident's identifier is
the hash of the operation that created it, so it carries no address and survives
them renaming or moving. We run [Bluesky's own
implementation](https://github.com/did-method-plc/did-method-plc) rather than
our reading of the spec — it is the software `plc.directory` runs, and it has
already caught a defect that our own tests agreed with for months.

> `./plc-serve reset` destroys every identity the directory ever issued. Their
> handles still resolve and their records still name them, and nothing can check
> any of it. That is why it is a separate word you have to type.

---

## First run

```sh
git clone --recurse-submodules https://github.com/StreetMesh/Server.git
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

**If you forget `--recurse-submodules`**, `composer install` prints
`Symlinking...`, **exits 0**, and never creates `vendor/streetmesh/`. Nothing
fails until a class is not found. Recover with:

```sh
git submodule update --init --recursive
composer install
```

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

---

## How installing is one step

Nothing has a registry. Four glob patterns find your package and one method
tells the server where your room is, which is why installing an experience needs
no wiring on the PHP side at all.

| What | Pattern | Where |
|---|---|---|
| PHP | `packages/*` path repo + `extra.laravel.providers` | `composer.json` |
| Screens | `Livewire::addNamespace(...)` in your provider | your provider |
| Browser JS | `../../packages/*/resources/js/alpine.js` | `resources/js/app.js` |
| Hub rooms | `Experience::room()`, collected by `php artisan hub:build` | your `Experience` |
| Styles | `../../packages/*/resources/views/**/*.blade.php` | `resources/css/app.css` |

`import.meta.glob` resolves when Vite builds its module graph, not in the
browser. If a newly installed package's components do not appear, **restart
Vite** before looking anywhere else.

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

`packages/*` are git submodules, mounted by a Composer path repository:

```json
"repositories": [{ "type": "path", "url": "packages/*" }]
```

Edit them **in `Server/packages/`**. That directory is the real checkout, not a
copy — commit and push from inside it, then bump the pointer in `Server`.

> **A package's own test suite does not see its siblings.** Each package
> resolves `streetmesh/*` from a git clone in its own `vendor/`. Change
> `protocol-laravel` and the venue's suite still tests last week's code until
> you `composer update streetmesh/protocol-laravel` inside the venue. This bites
> constantly and looks like a caching bug.

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

### "Class not found" after a clean clone
A missing submodule. `composer install` exits 0 and silently creates no
`vendor/streetmesh/`. → `git submodule update --init --recursive`

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
