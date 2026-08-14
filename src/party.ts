/**
 * A few people who came here together, kept in earshot of each other.
 *
 * The first room in this package that belongs to the hub rather than to an
 * experience, and the only one that will: everything else here is rules, and a
 * party has none. What it has is presence and a claim on the microphone, which
 * is exactly what `VenueRoom` already does — so almost nothing is added.
 *
 * It exists as a *second* room rather than as a mode of the first because that
 * is what a party is. Somebody in one is in two rooms at once: the table they
 * are at, and the people they arrived with. The table knows they are in a party
 * and shows it; the party knows nothing whatever about the table.
 *
 * Audio and video here supersede the room's own. That is not a policy this can
 * enforce — the browser holds both connections and decides what to render — but
 * it is not really a policy either. You cannot listen to two conversations, so
 * a person in a party is unhearable at the table by construction, and there is
 * never a second live channel for anything to hide in.
 *
 * How many people may be here is the venue's business and is not checked here.
 * The venue refuses to mint a ticket past its own cap; this room admits whoever
 * arrives holding one, which is the same division of labour every other room
 * follows — the venue decides who may be somewhere, and the hub checks only
 * that the venue said so.
 */

import { MapSchema, schema } from '@colyseus/schema'
import type { Client } from '@colyseus/core'
import { Occupant } from './presence.ts'
import { VenueRoom } from './room.ts'

/** Anything longer than this is not a room name, it is somebody testing. */
const SPACE_LIMIT = 200

export const PartyState = schema(
  {
    occupants: { map: Occupant },

    /**
     * Where each of them is right now, keyed by session like presence is.
     *
     * The client's own word about itself, and nothing turns on it — it is what
     * lets a party show "Aaron is at a chess table" instead of a list of names
     * doing nothing. Neither server ever asks the other: the browser is in both
     * rooms already, so it can say where it is without the venue reporting on
     * anybody, which is the arrangement that keeps a roster from becoming
     * surveillance.
     *
     * It is also the thing that makes calling people to you possible later. A
     * party that knows where its members are can offer to move them; one that
     * does not would have to grow a way to find out first.
     */
    spaces: { map: 'string' },
  },
  'PartyState',
)

type PartyStateType = InstanceType<typeof PartyState>

export class PartyRoom extends VenueRoom<PartyStateType> {
  protected opened(): void {
    this.state = new PartyState({
      occupants: new MapSchema<InstanceType<typeof Occupant>>(),
      spaces: new MapSchema<string>(),
    })

    /*
     * "I have moved." Sent by the browser as it walks between experiences, so
     * the rest of the party can see where everybody went.
     *
     * Trimmed and capped rather than validated. There is nothing to validate
     * against — the names of this venue's rooms are the venue's business and
     * this process has no list of them — and nothing is decided on the answer,
     * so the only thing worth defending against is somebody putting a novel in
     * the room's state.
     */
    this.onMessage('here', (client, message: unknown) => {
      const space = typeof message === 'string' ? message : (message as { space?: unknown })?.space

      if (typeof space !== 'string') {
        return
      }

      this.state.spaces.set(client.sessionId, space.trim().slice(0, SPACE_LIMIT))
    })
  }

  protected seated(client: Client): void {
    /*
     * Nowhere in particular, until they say. A member who joined the party from
     * the lobby and has not moved since is genuinely in no experience, and an
     * empty string says that better than an absent key — every other member is
     * in the map, and a missing one reads as somebody who has gone.
     */
    this.state.spaces.set(client.sessionId, '')
  }

  protected left(client: Client): void {
    this.state.spaces.delete(client.sessionId)
  }
}

export default { name: 'com.streetmesh.party', room: PartyRoom }
