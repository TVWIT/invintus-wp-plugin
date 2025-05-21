import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Panel, TextControl, ToggleControl, SelectControl } from '@wordpress/components';
import createUniqueId from './uid';
import { useEffect } from 'react';
import { InvintusIconFullColor, InvintusIconLegacy } from './components/InvintusIcon'; // Adjust the
import './editor.scss';
import { __ } from '@wordpress/i18n';

/**
 * Edit function for the Invintus block.
 *
 * This function defines the editing interface for the Invintus block within the WordPress editor.
 * It allows for the input of an event ID and toggling a simple event state, updating the embedded
 * iframe accordingly in a debounced manner to optimize performance.
 *
 * @param {Object}   props               The properties passed down to the component.
 * @param {Object}   props.attributes    Current attributes of the block, containing the event ID and simple event state.
 * @param {Function} props.setAttributes Function to update the attributes of the block.
 * @param {boolean}  props.isSelected    Indicates if the block is currently selected in the editor.
 * @returns {WPElement}                  The element to render, defining the block's edit layout.
 */
export default function Edit( { attributes, setAttributes, isSelected } ) {
  let isLegacy = false;

  // Handle both new format and legacy ACF format
  let eventId, isSimpleEvent, playerPrefId;

  if (attributes?.data) {
    isLegacy = true;
    // Legacy ACF format
    eventId = attributes.data.invintus_event_id ?? '';
    isSimpleEvent = attributes.data.invintus_event_is_simple ?? false;
    playerPrefId = attributes.data.invintus_player_pref_id ?? '';
  } else {
    // New format
    eventId = attributes?.invintus_event_id ?? '';
    isSimpleEvent = attributes?.invintus_event_is_simple ?? false;
    playerPrefId = attributes?.invintus_player_pref_id ?? '';
  }

  // Check if the global configuration object is set.
  const isConfigured = typeof invintusConfig !== 'undefined' && invintusConfig.clientId;

  // Transform player preferences object into array of options
  const playerPreferences = invintusConfig?.playerPreferences ? Object.entries(invintusConfig.playerPreferences).map(([value, label]) => ({
    value,
    label
  })).sort((a, b) => a.label.localeCompare(b.label)) : [];

  // Use WordPress's useBlockProps for block wrapper properties.
  const blockProps = useBlockProps( {
    className: isLegacy ? 'wp-block-taproot-invintus__legacy' : 'wp-block-taproot-invintus'
  } );

  /**
   * This effect is responsible for launching the Invintus player.
   * It is triggered when the eventId, isSimpleEvent, playerPrefId, or isConfigured variables change.
   * The effect is debounced by 300ms to prevent unnecessary updates.
   */
  useEffect( () => {
    // Set a timeout to debounce the iframe source update.
    const timeoutId = setTimeout( () => {
      // Only proceed if we have an event ID and the configuration is valid.
      if ( eventId && isConfigured ) {
        // Construct the configuration object for the iframe URL.
        const config = {
          clientID: invintusConfig.clientId,
          eventID: eventId,
          playerPrefID: playerPrefId || invintusConfig.defaultPlayerId,
          nonce: invintusConfig.nonce,
        };

        // Add the 'simple' parameter if applicable.
        if ( isSimpleEvent ) {
          config.simple = true;
        }

        // Launch the Invintus player with the constructed configuration.
        Invintus.launch( config )
      }
    }, 300 ); // 300ms delay for debouncing

    // Cleanup function to clear the timeout if the component unmounts or if attributes change again before the timeout completes.
    return () => {
      clearTimeout( timeoutId );
    };
  }, [eventId, isSimpleEvent, playerPrefId, isConfigured] );

  /**
   * Event handler for changes to the event ID input field.
   * This function is called every time the user types in the input field for the event ID.
   *
   * @param {string} value The new value from the input field.
   */
  const onChangeEventId = ( newEventId ) => {
    /**
     * This block of code is responsible for updating the `invintus_event_id` attribute of the component.
     * It first checks if the new event ID is different from the current one.
     * If they are different, it sets the `invintus_event_id` attribute to an empty string and then updates it to the new event ID.
     * This is done to force the component to re-render and update the javascript source.
     * The update to the new event ID is deferred using `setTimeout` with a delay of 0 to prevent potential flickering issues.
     * This allows the browser to finish any pending UI updates before re-rendering the component.
     */
    if ( newEventId !== eventId ) {
      // Handle both legacy ACF format and new format
      if (attributes?.data) {
        // Legacy ACF format - update the data object
        setAttributes( {
          data: {
            ...attributes.data,
            invintus_event_id: ''
          }
        } );
        setTimeout( () => {
          setAttributes( {
            data: {
              ...attributes.data,
              invintus_event_id: newEventId
            }
          } );
        }, 0 );
      } else {
        // New format
        setAttributes( { invintus_event_id: '' } );
        setTimeout( () => {
          setAttributes( { invintus_event_id: newEventId } );
        }, 0 );
      }
    }
  };

  /**
   * Event handler for changes to the simple event toggle control.
   * This function is called every time the user toggles the simple event state.
   *
   * @param {boolean} value The new state of the toggle control.
   */
  const onChangeIsSimple = ( value ) => {
    // Handle both legacy ACF format and new format
    if (attributes?.data) {
      // Legacy ACF format
      setAttributes( {
        data: {
          ...attributes.data,
          invintus_event_is_simple: value
        }
      } );
    } else {
      // New format
      setAttributes( { invintus_event_is_simple: value } );
    }
  };

  /**
   * Event handler for changes to the player preference dropdown.
   * This function is called every time the user selects a different player preference.
   *
   * @param {string} value The new player preference ID.
   */
  const onChangePlayerPref = ( value ) => {
    // Handle both legacy ACF format and new format
    if (attributes?.data) {
      // Legacy ACF format
      setAttributes( {
        data: {
          ...attributes.data,
          invintus_player_pref_id: value
        }
      } );
    } else {
      // New format
      setAttributes( { invintus_player_pref_id: value } );
    }
  };

  // Render the block's editing interface.
  return (
    <>
      <div {...blockProps}>
        {!isConfigured ? (
          <div className="invintus-config-message">{ __( 'Please configure the Invintus Client ID.', 'invintus' ) }</div>
        ) : (
          <div id={createUniqueId( 'invintus-video' )} className="invintus-block">
            {eventId ? (
              <div className="invintus-video-wrapper">
                <header>
                  {isLegacy ? <InvintusIconLegacy /> : <InvintusIconFullColor />}
                  <div className="invintus-event-label">
                    <span>{ __( 'Preview', 'invintus' ) }</span>
                  </div>
                  <div className="invintus-event-id">
                    {eventId}
                  </div>
                </header>
                <section>
                  <div className="invintus-player"></div>
                </section>
              </div>
            ) : (
              <div className="invintus-placeholder no-player">
                <div className="invintus-placeholder__notice">
                  <span>{ __( 'Enter a valid Event ID', 'invintus' ) }</span>
                </div>
              </div>
            )}
          </div>
        )}
      </div>
      {isSelected && isConfigured && (
        <InspectorControls key="settings">
          <Panel header="Event Settings">
            <PanelBody>
              <TextControl label="Event ID" value={eventId} onChange={onChangeEventId} />
              <SelectControl
                label={ __( 'Player Preference', 'invintus' ) }
                value={ playerPrefId }
                options={ [
                  { value: '', label: __( 'Global Player Preference', 'invintus' ) },
                  ...playerPreferences
                ] }
                onChange={ onChangePlayerPref }
                help={ __( 'Select a player preference or leave as default to use the global player preference.', 'invintus' ) }
              />
              <ToggleControl label="Use Simple Player" checked={isSimpleEvent} onChange={onChangeIsSimple} />
            </PanelBody>
          </Panel>
        </InspectorControls>
      )}
    </>
  );
}
