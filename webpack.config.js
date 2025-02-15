const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const { getWebpackEntryPoints } = require( '@wordpress/scripts/utils/config' );

module.exports = {
  ...defaultConfig,
  entry: {
    ...getWebpackEntryPoints(),
    sidebar: path.resolve( process.cwd(), 'src', 'sidebar.js' ), // additional entry for sidebar
    settings: path.resolve( process.cwd(), 'src', 'settings.js' ), // additional entry for settings
  },
};
