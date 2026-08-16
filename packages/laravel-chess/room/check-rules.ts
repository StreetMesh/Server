/**
 * Check that the room is the referee, against a running hub.
 *
 * Two things this exists to prove, both of which a board depends on and neither
 * of which any PHP test can see:
 *
 *   - the legal moves the room publishes are real and change with the position
 *   - a move from the side not to play is refused
 *
 * The second is the one that matters. The board also stops you, but the board
 * runs on somebody else's computer, so being polite there is not the same as
 * being safe here.
 *
 *   ./plc-serve && ./hub-serve      (in StreetMesh/Server)
 *   node --experimental-strip-types packages/laravel-chess/room/check-rules.ts
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

/** A real ticket, signed by the venue in PHP. Nothing here is faked. */
function mint(room: string, seat: string, who: string): string {
  const minted = JSON.parse(
    execFileSync('php', [resolve(here, '../../../bin/mint-ticket.php'), room, seat, who], {
      encoding: 'utf8',
    }),
  )

  return minted.ticket
}

const table = `rules-${process.pid}`
const client = new Client((process.env.HUB_URL ?? 'http://127.0.0.1:2567').replace(/^http/, 'ws'))

// Two people, not one person twice — nobody may take two seats, and a check
// that minted the same visitor twice would only ever discover that.
const white = await client.joinOrCreate(TYPE, { ticket: mint(table, 'white', 'alice'), room: table })
const black = await client.joinById(white.roomId, { ticket: mint(table, 'black', 'bob'), room: table })

await new Promise((settle) => setTimeout(settle, 500))

console.log('\nBefore anybody has moved')

const opening = [...white.state.legal]

report('white has twenty moves', opening.length === 20, `${opening.length} moves`)
report('e2e4 is among them', opening.includes('e2e4'))
report('it is white to play', white.state.turn === 'white', white.state.turn)

console.log('\nThe side not to play')

let refusal = ''
black.onMessage('refused', ({ because }: { because: string }) => {
  refusal = because
})

black.send('move', { from: 'e7', to: 'e5' })
await new Promise((settle) => setTimeout(settle, 400))

report('black cannot move first', refusal !== '', refusal || 'the room said nothing')
report('and the position did not change', white.state.moves.length === 0)

console.log('\nAfter a move')

white.send('move', { from: 'e2', to: 'e4' })
await new Promise((settle) => setTimeout(settle, 400))

report('the move stands', white.state.moves.length === 1, [...white.state.moves].join(' '))
report('it is black to play', white.state.turn === 'black', white.state.turn)

const answer = [...white.state.legal]

report('the moves published are black.s', answer.includes('e7e5'), `${answer.length} moves`)
report('and no longer white.s', !answer.includes('e2e4'))

console.log('\nWhat the other browser sees')

report('black sees the same position', black.state.fen === white.state.fen)
report('and the same legal moves', [...black.state.legal].length === answer.length)

await white.leave()
await black.leave()

console.log(failures === 0 ? '\nThe room is the referee.\n' : `\n${failures} failed.\n`)

process.exit(failures === 0 ? 0 : 1)
