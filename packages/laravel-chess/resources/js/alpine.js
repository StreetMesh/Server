/**
 * What this package puts on the page.
 *
 * Exported by name rather than registered here, because registering would mean
 * this file had an opinion about which framework the host uses — and a package
 * that reaches for a global is a package that only works in one application.
 *
 * The host finds this file, and every other one like it, by pattern. Adding a
 * component means adding it to this object; installing the package means
 * nothing at all.
 */

import chessTable, { chessReplay } from './table.js'

export default { chessTable, chessReplay }
