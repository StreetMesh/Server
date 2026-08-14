/**
 * Check that people who came here together stay together.
 *
 *   ./check-party
 *
 * Two things are being checked, and they are different questions. The first is
 * that a party is a room like any other: it opens for a ticket the venue signed
 * and for nothing else, and everybody the venue sent to one party lands in one
 * room. The second is the part that only matters because parties exist — that a
 * table can *see* somebody is in one.
 *
 * That second one is the whole of what stops a party being a hidden channel.
 * Somebody in a party has their voice superseded by it, which leaves them
 * present at the table and unhearable there; a table that could not show it
 * would be a table where people talk to a wall and cannot tell why.
 *
 * Every ticket here is minted by the venue in PHP, exactly as check-join's are.
 */

import { Client } from 'colyseus.js'
import { execFileSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'
import { listen } from '@colyseus/tools'
import { hub, typeNameFor } from '../src/mod.ts'
import { VenueRoom } from '../src/room.ts'

const here = dirname(fileURLToPath(import.meta.url))
const PORT = 2598
const PARTY = 'com.streetmesh.party'
const EXPERIENCE = 'com.streetmesh.games.chess'

let failures = 0

const check = (what: string, passed: boolean, detail = '') => {
  failures += passed ? 0 : 1
  console.log(`  ${passed ? '✓' : '✗'} ${what.padEnd(52)} ${detail}`)
}

/**
 * One real ticket, signed by the venue.
 *
 * `who` matters here in a way it does not in check-join: a party is several
 * people, and a room treats a second arrival under one subject as the same
 * person coming back — so two members minted under one name would be one member
 * throwing themselves out.
 */
function mint(room: string, seat: string, who: string, party = ''): { ticket: string } {
  return JSON.parse(
    execFileSync('php', [resolve(here, '../../bin/mint-ticket.php'), room, seat, who, party], {
      encoding: 'utf8',
    }),
  )
}

/** A table with no rules, which is all that is needed to look at its roster. */
class Table extends VenueRoom {}

/*
 * No experiences at all, and the party still has to be there.
 *
 * This is the claim being made by installing it in `hub()` rather than leaving
 * it to a venue: a party belongs to no experience, so a hub serving none of
 * them still serves this.
 */
const server = await listen(hub([{ name: EXPERIENCE, room: Table }]), PORT)

const client = new Client(`ws://127.0.0.1:${PORT}`)

console.log()

const partyRoom = `${PARTY}/01JQPARTY0000000000000000`

const first = await client
  .joinOrCreate(typeNameFor(PARTY), {
    ticket: mint(partyRoom, '', 'alice', partyRoom).ticket,
    room: partyRoom,
  })
  .then(
    (room) => room,
    (refused) => {
      check('a party ticket opens the party room', false, String(refused?.message ?? refused))

      return null
    },
  )

if (first) {
  check('a party ticket opens the party room', true, first.roomId)
}

/*
 * A ticket for a party is a ticket for *that* party. Nothing about a party
 * being a hub room rather than an experience's should soften the check every
 * other room gets.
 */
try {
  const wrong = await client.joinOrCreate(typeNameFor(PARTY), {
    ticket: mint(partyRoom, '', 'alice', partyRoom).ticket,
    room: `${PARTY}/01JQSOMEBODYELSES0000000`,
  })
  await wrong.leave()
  check("another party's ticket opens nothing", false, 'it was let in')
} catch (refused) {
  check("another party's ticket opens nothing", true, String((refused as Error).message).slice(0, 40))
}

const second = await client.joinOrCreate(typeNameFor(PARTY), {
  ticket: mint(partyRoom, '', 'bob', partyRoom).ticket,
  room: partyRoom,
})

check('two members reach one party', second.roomId === first?.roomId, second.roomId)

// Presence is state, so it arrives rather than being asked for.
await new Promise((settle) => setTimeout(settle, 200))

check(
  'and each of them can see who else is in it',
  [...second.state.occupants.values()].length === 2,
  [...second.state.occupants.values()].map((o: { name: string }) => o.name).join(', '),
)

/*
 * Where everybody is, which is the party's own state and nobody else's.
 *
 * The browser says it, because the browser is the only thing in both rooms —
 * no venue reports on anybody and no server asks another where somebody went.
 */
second.send('here', { space: `${EXPERIENCE}/01JQTABLE000000000000000` })

await new Promise((settle) => setTimeout(settle, 200))

const spaces = [...(first?.state.spaces.values() ?? [])] as string[]

check(
  'a member can say where they are',
  spaces.some((space) => space.startsWith(EXPERIENCE)),
  spaces.filter(Boolean).join(', ') || 'nobody anywhere',
)

/*
 * And now the part that matters. A table, with somebody at it who is in a
 * party, and everybody else at that table able to see so.
 */
const tableRoom = `${EXPERIENCE}/01JQTABLE000000000000000`

const table = await client.joinOrCreate(typeNameFor(EXPERIENCE), {
  ticket: mint(tableRoom, 'white', 'alice', partyRoom).ticket,
  room: tableRoom,
})

const alongside = await client.joinOrCreate(typeNameFor(EXPERIENCE), {
  ticket: mint(tableRoom, 'black', 'carol', '').ticket,
  room: tableRoom,
})

await new Promise((settle) => setTimeout(settle, 200))

const atTheTable = [...alongside.state.occupants.values()] as Array<{ name: string; party: string }>

check(
  'a table shows that somebody is in a party',
  atTheTable.some((occupant) => occupant.party === partyRoom),
  atTheTable.map((o) => `${o.name}:${o.party || 'alone'}`).join(', '),
)

check(
  'and shows that somebody else is not',
  atTheTable.some((occupant) => occupant.party === ''),
  'nobody is quietly in one',
)

await alongside.leave()
await table.leave()
await second.leave()

if (first) {
  await first.leave()
}

await server.gracefullyShutdown(false)

console.log()
console.log(
  failures === 0
    ? '  A party holds together, and a table can see one.\n'
    : `  ${failures} step(s) did not work.\n`,
)

process.exit(failures === 0 ? 0 : 1)
