/**
 * Use this file for JavaScript code that you want to run in the front-end
 * on posts/pages that contain this block.
 *
 * When this file is defined as the value of the `viewScript` property
 * in `block.json` it will be enqueued on the front end of the site.
 *
 * Example:
 *
 * ```js
 * {
 *   "viewScript": "file:./view.js"
 * }
 * ```
 *
 * If you're not making any changes to this file because your project doesn't need any
 * JavaScript running in the front-end, then you should delete this file and remove
 * the `viewScript` property from `block.json`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#view-script
 */

const invintusWP = ( () => {
  return {
    init() {
      this.whenReady( () => this.loadPlayers() )
    },
    // The player SDK (window.Invintus) is enqueued as a separate script with no
    // guaranteed execution order relative to this view script (WP defers block
    // view scripts by default). Poll briefly for the global before launching so
    // we never hit "Invintus is not defined" when this script wins the race.
    whenReady( cb, attempts = 0 ) {
      if ( typeof window.Invintus !== 'undefined' && typeof window.Invintus.launch === 'function' ) return cb()
      if ( attempts >= 200 ) { // ~10s at 50ms
        console.error( '[invintus] player SDK never loaded -- check the invintus-player-script <script> tag and its URL' )
        return
      }
      setTimeout( () => this.whenReady( cb, attempts + 1 ), 50 )
    },
    loadPlayers() {
      const $players = document.querySelectorAll( '.invintus-player' )

      if ( $players.length ) {
        $players.forEach( $player => {

          const config = {
            clientID: invintusConfig.clientId,
            playerPrefID: $player.dataset.playerid,
            eventID: $player.dataset.eventid,
            simple: $player.dataset.simple,
          }

          Invintus.launch( config )
        } )
      }
    },
  }
} )();

invintusWP.init();
