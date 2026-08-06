/**
 * What a StreetMesh hub is, said once.
 *
 * An ordinary Colyseus application — the options `@colyseus/tools` expects,
 * which is the shape everything that deploys Colyseus expects. What makes it a
 * hub rather than any other Colyseus server is two lines: the rooms a venue
 * installed, and the two questions that venue may ask about them.
 *
 * One definition, used three times: by the venue's generated `app.config.ts`,
 * by `hub-serve` locally, and by the checks in `bin/`. A hub assembled
 * differently in a test than in production would be a hub whose tests prove
 * something about a program nobody runs.
 *
 * Returns the options rather than calling `config()` on them. `config()`
 * validates and hands back what it was given, and it belongs in the generated
 * application next to `export default` — where every other Colyseus project
 * puts it, and where anybody reading it will look for it.
 */

import type { ConfigOptions } from '@colyseus/tools'
import { install, type Experience } from './install.ts'
import { routes } from './answers.ts'

/**
 * Anything the operator wants on top of what a hub always does.
 *
 * `initializeTransport` is the one worth naming. Colyseus decides it when
 * nothing says otherwise, which is what we want today — and WebTransport, when
 * we come to try it, is this option and nothing else. Welding a transport in
 * here is what the old hand-rolled server did, and it is what made the hub
 * awkward to deploy and impossible to change.
 */
export type HubOptions = Omit<ConfigOptions, 'initializeGameServer' | 'initializeExpress'> & {
  initializeGameServer?: ConfigOptions['initializeGameServer']
  initializeExpress?: ConfigOptions['initializeExpress']
}

export function hub(experiences: Experience[], also: HubOptions = {}): ConfigOptions {
  const { initializeGameServer, initializeExpress, ...rest } = also

  return {
    ...rest,

    initializeGameServer: (server) => {
      install(server, experiences)
      initializeGameServer?.(server)
    },

    initializeExpress: (app) => {
      routes(app)
      initializeExpress?.(app)
    },
  }
}
