/**
 * A room nobody enters without a ticket.
 *
 * This is the base every experience builds on, and the only thing it knows how
 * to do is check that a venue said somebody may sit down. It knows nothing
 * about chess, or shops, or whatever else gets built on it — and nothing about
 * federation either, which is the point: the venue resolved the address and
 * checked the delegation, and all that arrives here is a signature.
 *
 * What an experience adds is rules. What it never has to add is any of this.
 */

import { Room, type Client } from '@colyseus/core'
import { verifyTicket, type Ticket } from './ticket.ts'

export interface Seated {
  ticket: Ticket
}

export abstract class VenueRoom<State extends object = object> extends Room<State> {
  protected readonly seats = new Map<string, Ticket>()

  /**
   * Called before a client is admitted, and a throw here is a refusal rather
   * than an error — which is why everything deciding whether somebody may be
   * here belongs in it rather than in `onJoin`.
   */
  async onAuth(client: Client, options: { ticket?: string }): Promise<Seated> {
    if (typeof options?.ticket !== 'string' || options.ticket === '') {
      throw new Error('A seat here needs a ticket from the venue.')
    }

    /*
     * The room name is taken from this room rather than from the ticket, and
     * then compared inside. A ticket that named its own room would open any
     * room it was pointed at.
     */
    const ticket = await verifyTicket(options.ticket, this.roomId)

    /*
     * One seat, one occupant. Without this a second connection presenting the
     * same ticket would sit down beside the first, and the two would disagree
     * about the game from then on.
     */
    for (const [, seated] of this.seats) {
      if (seated.subject === ticket.subject) {
        throw new Error('Somebody is already sitting there.')
      }
    }

    return { ticket }
  }

  onJoin(client: Client, options: unknown, auth: Seated): void {
    this.seats.set(client.sessionId, auth.ticket)
    this.seated(client, auth.ticket)
  }

  onLeave(client: Client): void {
    const ticket = this.seats.get(client.sessionId)

    this.seats.delete(client.sessionId)

    if (ticket) {
      this.left(client, ticket)
    }
  }

  /** Who is here, as the venue vouched for them. */
  protected occupants(): Ticket[] {
    return [...this.seats.values()]
  }

  protected seated(client: Client, ticket: Ticket): void {}

  protected left(client: Client, ticket: Ticket): void {}
}
