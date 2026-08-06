/**
 * A game of chess, adjudicated here.
 *
 * This room is the authority on the rules. It decides whether a move stands,
 * whose turn it is, and when the game is over — and it refuses a browser that
 * says otherwise, because a browser is somebody else's computer.
 *
 * It is not the authority on what *happened*. It cannot sign anything and it
 * forgets everything when it stops. The venue is what makes a result durable,
 * and the record ends up in the players' own stores rather than here.
 *
 * The rules themselves are chess.js, because chess has been implemented
 * correctly many times and this is not the interesting part of the problem.
 * What is interesting is that they are enforced *here* rather than in the two
 * browsers that would each like to win.
 */

import { Chess } from 'chess.js'
import { MapSchema, schema } from '@colyseus/schema'
import type { Client } from '@colyseus/core'
import { Occupant, VenueRoom, type Ticket } from '../../hub/mod.ts'

const SEATS = ['white', 'black'] as const

export const ChessState = schema(
  {
    /** The position, in the notation every chess program already reads. */
    fen: 'string',

    /** Moves so far, so a screen can show the game rather than the position. */
    moves: ['string'],

    /**
     * The position after each of them, starting from the opening one.
     *
     * Kept so a finished game can be replayed without anybody else having to
     * know the rules. Working the positions out from the moves means a second
     * implementation of chess — in a browser, or in the venue — and the whole
     * arrangement here exists to avoid having two.
     *
     * This side already has them: it made each one deciding whether the move
     * was legal.
     */
    positions: ['string'],

    /** `white`, `black`, or empty once nobody is to move. */
    turn: 'string',

    /**
     * The side whose king is under attack, or empty.
     *
     * Said here rather than left to be worked out. A board could notice the `+`
     * on the end of a move, but that says a check happened and not which king
     * it is — finding that out means knowing where the kings are and which of
     * them is attacked, which is the rules again, in a browser.
     */
    check: 'string',

    /**
     * Every move that may be played right now, as `e2e4`.
     *
     * Published so a board can show somebody where a piece may go without
     * working it out. A browser that computed this would be a second
     * implementation of the rules, and two implementations are two chances to
     * disagree about who won — so the side that already decides what is legal
     * is the side that says so.
     *
     * Only ever the side to move's, which is the same list the room would
     * accept. It gives nothing away: it is derivable from the position by
     * anybody who cares to, and both players can see the position.
     */
    legal: ['string'],

    /** Empty while playing; otherwise how it ended. */
    outcome: 'string',
    winner: 'string',

    /**
     * The seat that has offered a draw, while the offer stands.
     *
     * The one thing here that needs both players to agree, so it is state
     * rather than a message: an offer made while somebody was reconnecting has
     * to still be there when they arrive.
     */
    drawOfferedBy: 'string',

    occupants: { map: Occupant },
  },
  'ChessState',
)

type ChessStateType = InstanceType<typeof ChessState>

export class ChessRoom extends VenueRoom<ChessStateType> {
  /** Two players and an audience. */
  maxClients = 16

  private game = new Chess()

  protected opened(): void {
    this.state = new ChessState({
      fen: this.game.fen(),
      moves: [],
      positions: [this.game.fen()],
      turn: 'white',
      check: '',
      legal: [],
      outcome: '',
      winner: '',
      drawOfferedBy: '',
      occupants: new MapSchema<InstanceType<typeof Occupant>>(),
    })

    // White has twenty moves before anybody has done anything, and a board
    // that showed none of them until the second move would look broken.
    this.publishLegalMoves()

    this.onMessage('move', (client, message: { from?: string; to?: string; promotion?: string }) => {
      this.play(client, message)
    })

    /*
     * The two ways a game ends that the rules have nothing to say about.
     *
     * Chess ends by checkmate or stalemate; games end because somebody gave up
     * or both agreed to stop. Without these a lost position has no ending
     * except abandonment, which is the commonest way a game of chess actually
     * finishes and the one that leaves nothing to write down.
     */
    this.onMessage('resign', (client) => this.resign(client))
    this.onMessage('draw-offer', (client) => this.offerDraw(client))
    this.onMessage('draw-accept', (client) => this.acceptDraw(client))
    this.onMessage('draw-decline', (client) => this.declineDraw(client))
  }

  /**
   * Which seat this client is playing, or nothing if they are watching.
   *
   * Everything below asks this first. A watcher who sent `resign` could
   * otherwise end somebody else's game from the audience.
   */
  private playerAt(client: Client): string {
    const seat = this.seats.get(client.sessionId)?.seat ?? ''

    return SEATS.includes(seat as never) && this.state.outcome === '' ? seat : ''
  }

  private resign(client: Client): void {
    const seat = this.playerAt(client)

    if (seat === '' || !this.bothHere()) {
      return
    }

    this.conclude('resignation', seat === 'white' ? 'black' : 'white')
  }

  /**
   * Whether there are two people playing rather than one person waiting.
   *
   * Resigning to nobody is not a thing that can happen. The board hides the
   * button, but the board is somebody else's computer — without this, a player
   * alone at a table could end a game that had not started, and the record
   * would say they lost one nobody played.
   */
  private bothHere(): boolean {
    return this.present().filter((who) => SEATS.includes(who.seat as never)).length >= 2
  }

  private offerDraw(client: Client): void {
    const seat = this.playerAt(client)

    // Offering again changes nothing, and offering into somebody else's open
    // offer is an acceptance in everything but name — so it is one.
    if (seat === '' || seat === this.state.drawOfferedBy) {
      return
    }

    if (this.state.drawOfferedBy !== '') {
      this.conclude('agreement', '')

      return
    }

    this.state.drawOfferedBy = seat
  }

  private acceptDraw(client: Client): void {
    const seat = this.playerAt(client)

    // Only the other player. Accepting your own offer would be resigning the
    // game to nobody.
    if (seat === '' || this.state.drawOfferedBy === '' || this.state.drawOfferedBy === seat) {
      return
    }

    this.conclude('agreement', '')
  }

  private declineDraw(client: Client): void {
    if (this.playerAt(client) === '') {
      return
    }

    this.state.drawOfferedBy = ''
  }

  /**
   * Over, for a reason the rules did not decide.
   *
   * Everything a finished game needs is set here rather than left to the
   * position: no turn, nothing legal, and an outcome the venue can write down.
   */
  private conclude(outcome: string, winner: string): void {
    this.state.outcome = outcome
    this.state.winner = winner
    this.state.turn = ''
    this.state.check = ''
    this.state.drawOfferedBy = ''
    this.state.legal.splice(0)

    /*
     * Nobody is leaving over this — both players may sit and look at the board
     * for a while — so the venue would not otherwise hear until the room was
     * disposed, and by then everybody who could have knocked has gone.
     */
    this.tell()
  }

  /**
   * A move, or a refusal.
   *
   * Every reason to say no is checked here rather than relied upon in the
   * screen. A screen that greys out the wrong squares is a bug; a screen that
   * could make an illegal move stand would be a different game.
   */
  private play(client: Client, move: { from?: string; to?: string; promotion?: string }): void {
    const ticket = this.seats.get(client.sessionId)

    if (!ticket) {
      return
    }

    if (this.state.outcome !== '') {
      client.send('refused', { because: 'That game is over.' })

      return
    }

    if (!SEATS.includes(ticket.seat as (typeof SEATS)[number])) {
      client.send('refused', { because: 'You are watching, not playing.' })

      return
    }

    if (ticket.seat !== this.state.turn) {
      client.send('refused', { because: 'It is not your turn.' })

      return
    }

    try {
      /*
       * chess.js throws on an illegal move rather than returning null, and
       * that is the whole enforcement: a browser can ask for anything and only
       * what the rules allow changes the position.
       */
      const played = this.game.move({
        from: String(move?.from),
        to: String(move?.to),
        promotion: move?.promotion ? String(move.promotion) : 'q',
      })

      this.state.moves.push(played.san)
      this.state.positions.push(this.game.fen())
    } catch {
      client.send('refused', { because: 'That is not a legal move.' })

      return
    }

    this.state.fen = this.game.fen()
    this.state.turn = this.game.turn() === 'w' ? 'white' : 'black'

    // chess.js reports check against whoever is now to move, which is the
    // side in trouble.
    this.state.check = this.game.isCheck() ? this.state.turn : ''

    // Playing on is an answer. An offer left standing across a move would be
    // accepted later against a position nobody offered it in.
    this.state.drawOfferedBy = ''

    this.publishLegalMoves()

    this.settleIfOver()
  }

  /**
   * Deciding it is over, and saying how.
   *
   * Only the outcome is written into state. Turning that into a record is the
   * venue's job, and the venue asks — this room never calls out, holds no
   * credential, and could not be believed if it did.
   */
  /**
   * What the venue will write down, once there is something to write.
   *
   * Null until the game is actually over, because the venue signs this into
   * somebody's own records and a result signed early is a lie that outlives
   * the game.
   *
   * The moves as well as the outcome, so the record is the game rather than
   * the scoreline — a record you can replay is worth keeping, and one that
   * says only "white won" is barely worth signing.
   *
   * And the positions, so replaying it needs no rules. Anybody who had to
   * derive them would be implementing chess a second time to read a game that
   * had already been decided.
   */
  result(): Record<string, unknown> | null {
    if (this.state.outcome === '') {
      return null
    }

    return {
      outcome: this.state.outcome,
      winner: this.state.winner,
      moves: [...this.state.moves],
      positions: [...this.state.positions],
      fen: this.state.fen,
    }
  }

  /**
   * What may be played from here.
   *
   * Recomputed rather than adjusted, because a list of legal moves that is
   * patched as the game goes on is a rules engine, and there is already one
   * of those two lines up.
   */
  private publishLegalMoves(): void {
    this.state.legal.splice(0)

    if (this.game.isGameOver()) {
      return
    }

    for (const move of this.game.moves({ verbose: true })) {
      this.state.legal.push(`${move.from}${move.to}`)
    }
  }

  private settleIfOver(): void {
    if (!this.game.isGameOver()) {
      return
    }

    this.state.turn = ''
    this.state.check = ''
    this.state.legal.splice(0)

    if (this.game.isCheckmate()) {
      this.state.outcome = 'checkmate'

      // chess.js reports whose turn it is; in checkmate that side has lost.
      this.state.winner = this.game.turn() === 'w' ? 'black' : 'white'

      this.tell()

      return
    }

    this.state.outcome = this.game.isStalemate()
      ? 'stalemate'
      : this.game.isInsufficientMaterial()
        ? 'insufficient material'
        : this.game.isThreefoldRepetition()
          ? 'repetition'
          : 'draw'

    this.state.winner = ''

    this.tell()
  }

  protected seated(client: Client, ticket: Ticket): void {
    client.send('seated', { seat: ticket.seat, watching: !SEATS.includes(ticket.seat as never) })
  }
}

export default { name: 'com.streetmesh.games.chess', room: ChessRoom }
