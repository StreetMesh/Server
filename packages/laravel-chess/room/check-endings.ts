/**
 * The two ways a game ends that the rules have nothing to say about.
 *
 * Chess ends by checkmate or stalemate. *Games* end because somebody gave up or
 * both agreed to stop — and without those, a lost position has no ending except
 * abandonment, which is how most games of chess actually finish and the one
 * outcome that leaves nothing to write down.
 *
 * Mostly a check on who may not do what. A watcher who could resign would be
 * ending somebody else's game from the audience, and a player who could accept
 * their own offer would be drawing unilaterally.
 *
 *   ./plc-serve && ./hub-serve      (in StreetMesh/Server)
 *   node --experimental-strip-types packages/laravel-chess/room/check-endings.ts
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
  return JSON.parse(
    execFileSync('php', [resolve(here, '../../../bin/mint-ticket.php'), room, seat, who], {
      encoding: 'utf8',
    }),
  ).ticket
}

const settle = (ms = 350) => new Promise((done) => setTimeout(done, ms))

/*
 * The hub this asks, which is usually the one `./hub-serve` starts. Nameable so
 * a check can be run against a second hub on another port without stopping the
 * one already serving a browser.
 */
const client = new Client((process.env.HUB_URL ?? 'http://127.0.0.1:2567').replace(/^http/, 'ws'))

async function table(name: string) {
  const white = await client.joinOrCreate(TYPE, { ticket: mint(name, 'white', 'alice'), room: name })
  const black = await client.joinById(white.roomId, { ticket: mint(name, 'black', 'bob'), room: name })
  await settle()

  return { white, black }
}

console.log('\nResigning')
{
  const { white, black } = await table(`resign-${process.pid}`)

  white.send('resign')
  await settle()

  report('the game is over', white.state.outcome === 'resignation', white.state.outcome || 'still going')
  report('the other player won', white.state.winner === 'black', white.state.winner || 'nobody')
  report('nobody is to move', white.state.turn === '')
  report('and nothing is legal', [...white.state.legal].length === 0)

  await white.leave()
  await black.leave()
}

/*
 * The case this is actually used in.
 *
 * Somebody's opponent closes their tab and never comes back. The seat is still
 * theirs — that is what lets anybody return to their own game — so the board
 * goes on offering Resign, and it has to work. This asked who was *connected*,
 * which meant confirming a resignation and watching nothing happen at all.
 */
console.log('\nResigning a game the other player walked away from')
{
  const name = `abandoned-${process.pid}`
  const { white, black } = await table(name)

  await black.leave()
  await settle()

  white.send('resign')
  await settle()

  report('the game can still be given up', white.state.outcome === 'resignation', white.state.outcome || 'still going')
  report('and the absent player won it', white.state.winner === 'black', white.state.winner || 'nobody')

  await white.leave()
}

/*
 * And why the guard is there at all. One seat is somebody waiting rather than
 * somebody losing, and a table that could be resigned before anybody arrived
 * would write a defeat into the records for a game nobody played.
 */
console.log('\nA table with nobody to resign to')
{
  const name = `alone-${process.pid}`
  const white = await client.joinOrCreate(TYPE, { ticket: mint(name, 'white', 'alice'), room: name })
  await settle()

  white.send('resign')
  await settle()

  report('a game nobody joined cannot be lost', white.state.outcome === '', white.state.outcome || 'still going')

  await white.leave()
}

console.log('\nA draw, which takes two')
{
  const { white, black } = await table(`draw-${process.pid}`)

  white.send('draw-offer')
  await settle()

  report('the offer is on the record', white.state.drawOfferedBy === 'white', white.state.drawOfferedBy || 'nothing')
  report('and the game continues', white.state.outcome === '')

  // Nobody draws by agreeing with themselves.
  white.send('draw-accept')
  await settle()

  report('you cannot accept your own offer', white.state.outcome === '', white.state.outcome || 'still going')

  black.send('draw-accept')
  await settle()

  report('the other player can', white.state.outcome === 'agreement', white.state.outcome || 'still going')
  report('and nobody won', white.state.winner === '')

  await white.leave()
  await black.leave()
}

console.log('\nPlaying on')
{
  const { white, black } = await table(`declined-${process.pid}`)

  white.send('draw-offer')
  await settle()
  black.send('draw-decline')
  await settle()

  report('a declined offer is gone', white.state.drawOfferedBy === '')
  report('and the game continues', white.state.outcome === '')

  // An offer must not outlive the position it was made in.
  white.send('draw-offer')
  await settle()
  white.send('move', { from: 'e2', to: 'e4' })
  await settle()

  report('and a move withdraws one', white.state.drawOfferedBy === '', white.state.drawOfferedBy || 'gone')

  await white.leave()
  await black.leave()
}

console.log('\nFrom the audience')
{
  const name = `watching-${process.pid}`
  const { white, black } = await table(name)
  const watcher = await client.joinById(white.roomId, { ticket: mint(name, '', 'carol'), room: name })
  await settle()

  watcher.send('resign')
  watcher.send('draw-offer')
  await settle()

  report('a watcher cannot resign somebody else.s game', white.state.outcome === '', white.state.outcome || 'still going')
  report('nor offer a draw in it', white.state.drawOfferedBy === '', white.state.drawOfferedBy || 'nothing')

  await watcher.leave()
  await white.leave()
  await black.leave()
}

console.log(failures === 0 ? '\nA game can be ended by the people playing it.\n' : `\n${failures} failed.\n`)

process.exit(failures === 0 ? 0 : 1)
