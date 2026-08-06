/**
 * Who is in the room, as everybody in it sees them.
 *
 * The one piece of state every experience needs and none should have to write.
 * A game wants to know when the second player arrives, a watch party wants to
 * show a list, an auction wants to know who is still bidding — and all three
 * are asking the same question.
 *
 * It is derived entirely from tickets, which means it is the venue's word
 * rather than anybody's self-description. A participant cannot rename
 * themselves here, cannot claim a seat they were not given, and cannot appear
 * twice.
 *
 * Defined without decorators on purpose: decorators are not type syntax, so a
 * file using them cannot be run by Node's own type stripping — and the checks
 * in `bin/` do exactly that. This costs nothing and keeps one toolchain.
 */

import { MapSchema, schema } from '@colyseus/schema'

export const Occupant = schema({
  /** Their permanent identifier. Survives them changing their handle. */
  did: 'string',

  /** What to call them, as the venue vouched for it. */
  name: 'string',

  /**
   * Which seat, where an experience has seats. Empty for the people a watch
   * party would call an audience — present, but not playing.
   */
  seat: 'string',
})

export const Occupancy = schema({
  /*
   * Keyed by session rather than by DID, because one person may legitimately
   * have two connections — a phone and a laptop — and the room has to be able
   * to tell which one just went away.
   */
  occupants: { map: Occupant },
})

export type OccupantType = InstanceType<typeof Occupant>
export type OccupancyType = InstanceType<typeof Occupancy>

export function occupantsAsMap(): MapSchema<OccupantType> {
  return new MapSchema<OccupantType>()
}
