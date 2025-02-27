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
      this.loadPlayers()
    },
    loadPlayers() {
      const $players = document.querySelectorAll( '.invintus-player' )

      if ( $players.length ) {
        $players.forEach( $player => {

          const config = {
            clientID: invintusConfig.clientId,
            playerPrefID: invintusConfig.defaultPlayerId,
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
