/**
 * Registers a new block provided a unique name and an object defining its behavior.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * All files containing `style` keyword are bundled together. The code used
 * gets applied both to the front of your site and to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './style.scss';

/**
 * Internal dependencies
 */
import Edit from './edit';
import metadata from './block.json';
import { InvintusIconFullColor, InvintusIconLegacy } from './components/InvintusIcon';

/**
 * Every block starts by registering a new block type definition.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-registration/
 */
registerBlockType( metadata.name, {
  /**
   * @see ./edit.js
   */
  edit: Edit,
  icon: InvintusIconFullColor,
} );

// Register the legacy ACF block type directly
registerBlockType( 'acf/invintus-event', {
  title: 'Invintus Video (Legacy)',
  icon: InvintusIconLegacy,
  category: 'widgets',
  attributes: {
    data: {
      type: 'object',
      default: {}
    }
  },
  edit: Edit,
  save() {
    return null; // Server-side rendering
  }
} );

