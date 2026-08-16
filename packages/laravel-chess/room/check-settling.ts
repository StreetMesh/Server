/**
 * Play a game to its end, and check the hub will say so.
 *
 * The half of settling that lives out here. Two people play a real game to
 * checkmate against a real room, and then the question a venue asks is asked:
 * how did this end?
 *
 * What it does *not* do is write records — that needs somebody's own server to
 * write to, and this has none. The venue's half is covered by SettleTest.
 *
 *   ./plc-serve && ./hub-serve      (in StreetMesh/Server)
 *   node --experimental-strip-types packages/laravel-chess/room/check-settling.ts
 */

import { execFileSync } from 'node:child_process'
import { Client } from 'colyseus.js'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const TYPE = 'com_streetmesh_games_chess'
/*
 * The hub this asks, which is usually the one `./hub-serve` starts. Nameable so
 * a check can be run against a second hub on another port without stopping the
 * one already serving a browser.
 *
 * One address rather than two. This asks the hub over both a websocket and
 * HTTP, and when they were separate constants a check pointed somewhere else
 * quietly went on asking the old hub for the result of a game it had never
 * heard of.
 */
const HUB = process.env.HUB_URL ?? 'http://127.0.0.1:2567'

let failures = 0

function report(what: string, passed: boolean, detail = ''): void {
  console.log(`  ${passed ? 'ok  ' : 'FAIL'}  ${what}${detail ? `\n          ${detail}` : ''}`)

  if (!passed) {
    failures += 1
  }
}

function mint(room: string, seat: string, who: string): string {
  const minted = JSON.parse(
    execFileSync('php', [resolve(here, '../../../bin/mint-ticket.php'), room, seat, who], {
      encoding: 'utf8',
    }),
  )

  return minted.ticket
}

async function ask(room: string): Promise<Record<string, unknown> | null> {
  const answer = await fetch(`${HUB}/result?room=${encodeURIComponent(room)}`)
  const body = await answer.json()

  return answer.status === 200 ? body : null
}

const table = `settle-${process.pid}`
const client = new Client(HUB.replace(/^http/, 'ws'))

const white = await client.joinOrCreate(TYPE, { ticket: mint(table, 'white', 'alice'), room: table })
const black = await client.joinById(white.roomId, { ticket: mint(table, 'black', 'bob'), room: table })

await new Promise((settle) => setTimeout(settle, 400))

console.log('\nWhile it is being played')

report('the hub says nothing', (await ask(table)) === null)

console.log('\nScholar’s mate')

// The shortest game worth playing: four moves each, and the venue has
// something to write down.
const game: Array<[string, string]> = [
  ['e2', 'e4'],
  ['e7', 'e5'],
  ['f1', 'c4'],
  ['b8', 'c6'],
  ['d1', 'h5'],
  ['g8', 'f6'],
  ['h5', 'f7'],
]

for (const [index, [from, to]] of game.entries()) {
  ;(index % 2 === 0 ? white : black).send('move', { from, to })
  await new Promise((settle) => setTimeout(settle, 250))
}

report('it is over', white.state.outcome !== '', white.state.outcome || 'still going')
report('white won', white.state.winner === 'white', white.state.winner || 'nobody')
report('by checkmate', white.state.outcome === 'checkmate', white.state.outcome)

console.log('\nWhat the venue is told')

const result = await ask(table)

report('the hub answers now', result !== null)

if (result) {
  report('with the outcome', result.outcome === 'checkmate', String(result.outcome))
  report('with the winner', result.winner === 'white', String(result.winner))
  report(
    'and with the game, not just the score',
    Array.isArray(result.moves) && result.moves.length === 7,
    (result.moves as string[])?.join(' '),
  )
  report('and the final position', typeof result.fen === 'string' && result.fen.includes('Q'), String(result.fen))
}

console.log('\nWhen everybody has gone')

await white.leave()
await black.leave()
await new Promise((settle) => setTimeout(settle, 600))

/*
 * The room is disposed once it is empty, and the venue can no longer ask. This
 * is the durability question, not a defect: nothing here survives the hub, so a
 * venue that never asked has missed its chance.
 */
report('the hub has forgotten it', (await ask(table)) === null, 'in-memory only, by design for now')

console.log(failures === 0 ? '\nThe referee can be asked.\n' : `\n${failures} failed.\n`)

process.exit(failures === 0 ? 0 : 1)
