/**
 * Check that somebody watching sees the game.
 *
 * Two things a board depends on and neither of which any PHP test can reach:
 * a person with no seat is let in at all, and what they are shown is the game
 * already in progress rather than a fresh board.
 *
 * The second is the one worth guarding. Landing in a *different* room with the
 * same name looks almost right — there is a board, it is just the wrong one —
 * and that is much harder to notice than being refused.
 *
 *   ./plc-serve && ./hub-serve      (in StreetMesh/Server)
 *   node --experimental-strip-types packages/laravel-chess/room/check-watching.ts
 */

import { execFileSync } from 'node:child_process'
import { Client } from 'colyseus.js'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const TYPE = 'com_streetmesh_games_chess'

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

const table = `watch-${process.pid}`
const client = new Client((process.env.HUB_URL ?? 'http://127.0.0.1:2567').replace(/^http/, 'ws'))

const white = await client.joinOrCreate(TYPE, { ticket: mint(table, 'white', 'alice'), room: table })
const black = await client.joinById(white.roomId, { ticket: mint(table, 'black', 'bob'), room: table })

await new Promise((settle) => setTimeout(settle, 400))

white.send('move', { from: 'e2', to: 'e4' })
await new Promise((settle) => setTimeout(settle, 400))

console.log('\nSomebody with no seat')

let refused = ''
let watcher

try {
  watcher = await client.joinOrCreate(TYPE, { ticket: mint(table, '', 'carol'), room: table })
} catch (declined) {
  refused = (declined as Error).message
}

report('is let in', refused === '' && watcher !== undefined, refused)

if (watcher) {
  await new Promise((settle) => setTimeout(settle, 400))

  report('lands in the game already going', watcher.roomId === white.roomId, `${watcher.roomId} vs ${white.roomId}`)
  report('sees the move that was played', [...watcher.state.moves].join(' ') === 'e4', [...watcher.state.moves].join(' ') || 'nothing')
  report('sees the same position', watcher.state.fen === white.state.fen)
  report('and it is black to play', watcher.state.turn === 'black', watcher.state.turn)

  console.log('\nAnd cannot play')

  let told = ''
  watcher.onMessage('refused', ({ because }: { because: string }) => {
    told = because
  })

  watcher.send('move', { from: 'e7', to: 'e5' })
  await new Promise((settle) => setTimeout(settle, 400))

  report('a watcher moving is refused', told !== '', told || 'the room said nothing')
  report('and the game did not change', [...white.state.moves].join(' ') === 'e4')

  await watcher.leave()
}

await white.leave()
await black.leave()

console.log(failures === 0 ? '\nWatching works.\n' : `\n${failures} failed.\n`)

process.exit(failures === 0 ? 0 : 1)
