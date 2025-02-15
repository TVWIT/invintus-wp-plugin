// External dependencies
import { useState, useEffect, useRef } from '@wordpress/element';
import { createRoot } from 'react-dom/client';
import sortBy from 'lodash/sortBy';
import debounce from 'lodash/debounce';

// WordPress dependencies
import { __ } from '@wordpress/i18n';
import {
  // Basic UI components
  Button,

  // Card components
  Card,
  CardHeader,
  CardBody,
  CardDivider,
  CardFooter,

  // Layout components
  Flex,
  FlexItem,

  // Other components
  __experimentalHeading as Heading,
  Snackbar,
  Dashicon,
  Spinner,
} from '@wordpress/components';

// Internal dependencies
import InvintusToolbar from './components/Toolbar';
import SectionGeneral from './components/SectionGeneral';
import SectionRedirects from './components/SectionRedirects';
import SectionLogs from './components/SectionLogs';

// Styles
import './settings.scss';

/**
 * Creates an array of options for log retention.
 * Each option is an object with 'value' and 'label' properties.
 * @type {Array}
 */
const logRetentionOptions = [
  { value: '', label: __( 'Select', 'invintus' ) },
  { value: '1', label: __( '1 day', 'invintus' ) },
  { value: '7', label: __( '7 days', 'invintus' ) },
  { value: '30', label: __( '30 days', 'invintus' ) },
  { value: '90', label: __( '90 days', 'invintus' ) },
  { value: 'x', label: __( 'Forever', 'invintus' ) },
];

/**
 * Sanitizes a path by removing leading and trailing slashes and replacing multiple slashes with a single slash.
 * @param {string} path - The path to sanitize.
 * @return {string} The sanitized path.
 */
const sanitizePath = ( path ) => {
  return path.replace( /^\/+|\/+$/g, '' ).replace( /\/+/g, '/' );
};

const InvintusSettings = () => {
  // State variables for the settings form
  const [clientId, setClientId]                               = useState( '' );     // Client ID
  const [apiKey, setApiKey]                                   = useState( '' );     // API Key
  const [playerPreferences, setPlayerPreferences]             = useState( [] );     // Player Preferences
  const [enablePublicEvents, setEnablePublicEvents]           = useState( false );  // Enable Public Events
  const [watchRedirectPath, setWatchRedirectPath]             = useState( '' );     // Watch Redirect Path
  const [enableLogs, setEnableLogs]                           = useState( false );  // Enable Logs
  const [logRetention, setLogRetention]                       = useState( '' );     // Log Retention

  const [defaultPlayerPreference, setDefaultPlayerPreference] = useState( '' );     // Default Player Preference

  const [snackbarMessage, setSnackbarMessage]                 = useState( null );   // Snackbar Message
  const [isLoading, setIsLoading]                             = useState( false );  // Is Loading
  const [isSaving, setIsSaving]                               = useState( false );  // Is Saving
  const [isPurging, setIsPurging]                             = useState( false );  // Is Purging
  const [isPurged, setIsPurged]                               = useState( false );  // Is Purged

  // Reference for the save snackbar timeout
  const saveSnackbarTimeoutRef = useRef();

  // Reference for the purge snackbar timeout
  const purgeSnackbarTimeoutRef = useRef();

  useEffect( () => {
    // Fetch the settings when the component mounts
    fetchSettings();

    return () => {
      // Cancel the save operation and clear the timeouts when the component unmounts
      handleSave.cancel();
      clearTimeout( saveSnackbarTimeoutRef.current );
      clearTimeout( purgeSnackbarTimeoutRef.current );
    };
  }, [] );

  /**
   * Restructures the player preferences into an array of options.
   *
   * This function first creates an array with a default option.
   * Then, it sorts the player preferences by label in ascending order.
   * It maps each player preference to an object with 'value' and 'label' properties.
   * Finally, it returns the array of options.
   *
   * @param {Object} playerPreferences The player preferences.
   * @return {Array} The array of options.
   */
  const restructurePlayerPreferences = ( playerPreferences ) => {
    const options = [{ value: '', label: __( 'Select', 'invintus' ) }]
      .concat( sortBy( Object.entries( playerPreferences ), ( [, label] ) => label.toLowerCase() )
        .map( ( [value, label] ) => ( { value, label } ) ) );
    return options;
  };

  /**
   * Fetches the settings from the server.
   *
   * This function first sets the 'isLoading' state to true.
   * Then, it sends a GET request to the '/wp-json/invintus/v2/settings' endpoint.
   * If the request is successful, it updates the state with the fetched settings.
   * If the request fails, it logs the error to the console.
   * Finally, it sets the 'isLoading' state to false.
   */
  const fetchSettings = () => {
    setIsLoading( true );
    fetch( '/wp-json/invintus/v2/settings', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': invintusConfig.nonce,
      },
    } )
      .then( response => {
        if ( !response.ok ) {
          throw new Error( 'Network response was not ok' );
        }
        return response.json();
      } )
      .then( data => {
        if ( data ) {
          const settings = data;
          setClientId( settings.invintus_client_id || '' );
          setApiKey( settings.invintus_api_key || '' );
          setDefaultPlayerPreference( settings.invintus_player_preference_default || '' );
          setEnablePublicEvents( settings.can_public_future_events === '1' );
          setWatchRedirectPath( settings.invintus_watch_path || '' );
          setEnableLogs( settings.can_log_payloads === '1' );
          setLogRetention( settings.invintus_log_retention || '' );
          if ( settings.invintus_player_preferences ) {
            const options = restructurePlayerPreferences( settings.invintus_player_preferences );
            setPlayerPreferences( options );
          }
        }
      } )
      .catch( error => {
        console.error( 'There has been a problem with your fetch operation:', error );
      } )
      .finally( () => setIsLoading( false ) );
  };

  /**
   * Handles the save operation.
   *
   * This function is debounced to prevent rapid, successive calls.
   * It first checks if a save operation is already in progress or if the settings are still loading. If either is true, it returns immediately.
   * Then, it sets the 'isSaving' state to true and constructs the settings data to be saved.
   * It sends a POST request to the '/wp-json/invintus/v2/settings' endpoint with the settings data.
   * If the request is successful, it shows a snackbar for 3 seconds.
   * If the request fails, it logs the error to the console.
   * Finally, it sets the 'isSaving' state to false.
   */
  const handleSave = debounce( () => {
    if ( isSaving || isLoading ) return;

    setIsSaving( true );

    const settingsData = {
      clientId,
      apiKey,
      defaultPlayerPreference,
      enablePublicEvents,
      watchRedirectPath,
      enableLogs,
      logRetention,
    };

    fetch( '/wp-json/invintus/v2/settings', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': invintusConfig.nonce,
      },
      body: JSON.stringify( settingsData ),
    } )
      .then( response => {
        if ( !response.ok ) {
          throw new Error( 'Network response was not ok' );
        }
        return response.json();
      } )
      .then( data => {
        if ( data ) {
          setSnackbarMessage( __( 'Settings saved.', 'invintus' ) );
          clearTimeout( saveSnackbarTimeoutRef.current ); // Clear any existing timeout
          saveSnackbarTimeoutRef.current = setTimeout( () => {
            setSnackbarMessage( null );
          }, 5000 );
        }
      } )
      .catch( error => {
        console.error( 'There has been a problem with your fetch operation:', error );
      } )
      .finally( () => setIsSaving( false ) );
  }, 500 );

  /**
   * Handles the purge operation.
   *
   * This function is currently a placeholder and only logs 'Purge & Refresh' to the console.
   */
  const handlePurge = () => {
    setIsPurging( true );

    fetch( '/wp-json/invintus/v2/settings/player_prefs', {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': invintusConfig.nonce,
      },
    } )
      .then( response => {
        if ( !response.ok ) {
          throw new Error( 'Network response was not ok' );
        }
        return response.json();
      } )
      .then( data => {
        if ( data ) {
          const options = restructurePlayerPreferences( data );
          setPlayerPreferences( options );
        }
      } )
      .catch( error => {
        console.error( 'There has been a problem with your fetch operation:', error );
      } )
      .finally( () => {
        // Set isPurging to false to indicate that purging is complete
        setIsPurging( false );

        // Set isPurged to true to indicate that the data has been purged
        setIsPurged( true );

        // Clear any existing timeout to ensure that the snackbar is shown for a full 5 seconds after this purge
        clearTimeout( purgeSnackbarTimeoutRef.current );

        // Set a timeout to automatically hide the snackbar after 5 seconds
        purgeSnackbarTimeoutRef.current = setTimeout( () => {
          // After 5 seconds, set isPurged back to false
          setIsPurged( false );
        }, 5000 );
      } );
  };

  /**
   * Handles the blur event of the watch redirect path input.
   *
   * This function sanitizes the watch redirect path by removing leading and trailing slashes and replacing multiple slashes with a single slash.
   * It then sets the sanitized path as the new watch redirect path.
   */
  const handleWatchRedirectPathBlur = () => {
    setWatchRedirectPath( sanitizePath( watchRedirectPath ) );
  };

  return (
    <>
      <div className="snackbar-container">
        { snackbarMessage && (
          <Snackbar>
            <Flex align="center">
              <Dashicon icon="saved" />
              { snackbarMessage }
            </Flex>
          </Snackbar>
        ) }
      </div>
      <InvintusToolbar />
      <Card className="invintus-app-settings-fields" size="medium">
        <CardHeader>
          <Flex justify="space-between">
            <Heading level={ 3 }>{ __( 'Invintus Settings', 'invintus' ) }</Heading>
            <FlexItem>
              <Flex align="center">
                { ( isSaving ) && <Spinner /> }
                <Button variant="primary" onClick={ handleSave } disabled={ isSaving || isLoading }>{ __( 'Save', 'invintus' ) }</Button>
              </Flex>
            </FlexItem>
          </Flex>
        </CardHeader>
        { isLoading ? (
          <CardBody>
            <Spinner />
          </CardBody>
        ) : (
          <>
            <SectionGeneral
              clientId={ clientId }
              setClientId={ setClientId }
              apiKey={ apiKey }
              setApiKey={ setApiKey }
              defaultPlayerPreference={ defaultPlayerPreference }
              setDefaultPlayerPreference={ setDefaultPlayerPreference }
              playerPreferences={ playerPreferences }
              handlePurge={ handlePurge }
              isPurging={ isPurging }
              isPurged={ isPurged }
              enablePublicEvents={ enablePublicEvents }
              setEnablePublicEvents={ setEnablePublicEvents }
            />
            <CardDivider />
            <SectionRedirects
              watchRedirectPath={ watchRedirectPath }
              setWatchRedirectPath={ setWatchRedirectPath }
              handleWatchRedirectPathBlur={ handleWatchRedirectPathBlur }
              invintusConfig={ invintusConfig }
            />
            <CardDivider />
            <SectionLogs
              enableLogs={ enableLogs }
              setEnableLogs={ setEnableLogs }
              logRetention={ logRetention }
              setLogRetention={ setLogRetention }
              logRetentionOptions={ logRetentionOptions }
            />
          </>
        ) }
        <CardFooter>
          <Flex justify="end">
            { ( isSaving ) && <Spinner /> }
            <Button variant="primary" onClick={ handleSave } disabled={ isSaving || isLoading }>{ __( 'Save', 'invintus' ) }</Button>
          </Flex>
        </CardFooter>
      </Card>
    </>
  );
};

export default InvintusSettings;

document.addEventListener( 'DOMContentLoaded', () => {
  const container = document.querySelector( '#invintus-app-settings' );
  if ( container ) {
    const root = createRoot( container ); // Use createRoot
    root.render( <InvintusSettings /> );
  }
} )
