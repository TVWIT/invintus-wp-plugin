// WordPress dependencies
import { __ } from '@wordpress/i18n';
import {
  // Basic UI components
  Button,
  TextControl,
  SelectControl,
  ToggleControl,

  // Card components
  CardHeader,
  CardBody,
  CardDivider,

  // Layout components
  Flex,
  FlexBlock,

  // Other components
  __experimentalHeading as Heading,
  Dashicon,
  Spinner,
  ExternalLink,
} from '@wordpress/components';

const SectionGeneral = ( { clientId, setClientId, apiKey, setApiKey, defaultPlayerPreference, setDefaultPlayerPreference, playerPreferences, handlePurge, isPurging, isPurged, enablePublicEvents, setEnablePublicEvents } ) => {
  return (
    <>
      <CardHeader isBorderless="true">
        <Heading level={ 4 }>{ __( 'General', 'invintus' ) }</Heading>
      </CardHeader>
      <CardBody>
        { __( 'These setting can be found in your', 'text-domain' ) } <ExternalLink href="https://controlcenter.invintusmedia.com/account">{ __( 'Invintus Media - Control Center under your Account Settings', 'text-domain' ) }</ExternalLink>.
      </CardBody>
      <CardBody>
        <Flex className="invintus-app-settings-fields__credentials" gap="2">
          <FlexBlock>
            <TextControl
              label={ __( 'Client ID', 'invintus' ) }
              value={ clientId }
              onChange={ ( value ) => setClientId( value ) }
              help={ __( 'Enter your client ID here.', 'invintus' ) }
            />
          </FlexBlock>
          <FlexBlock>
            <TextControl
              label={ __( 'API Key', 'invintus' ) }
              value={ apiKey }
              onChange={ ( value ) => setApiKey( value ) }
              type="password"
              help={ __( 'Enter your API key here. It will be kept secret.', 'invintus' ) }
            />
          </FlexBlock>
        </Flex>
      </CardBody>
      <CardDivider />
      <CardBody>
        <Flex className="invintus-app-settings-fields__player_preferences" direction="column" gap="2">
          <SelectControl
            label={ __( 'Default Player Preference', 'invintus' ) }
            value={ defaultPlayerPreference }
            options={ playerPreferences?.length ? playerPreferences : [
              { value: '', label: __( 'No player preferences available', 'invintus' ) },
            ] }
            onChange={ ( value ) => setDefaultPlayerPreference( value ) }
            help={
              playerPreferences?.length
                ? __( 'Select your default player preference.', 'invintus' )
                : __( 'No player preferences available. Please check your API credentials and try refreshing the page.', 'invintus' )
            }
            disabled={ !playerPreferences?.length }
          />
          <Flex justify="end">
            <Button variant="secondary" onClick={ handlePurge }>
              <Flex align="center" gap="1">
                { __( 'Purge & Refresh', 'invintus' ) }
                { isPurging ? <Spinner /> : ( isPurged && !isPurging ) ? <Dashicon icon="saved" /> : <Dashicon icon="trash" /> }
              </Flex>
            </Button>
          </Flex>
        </Flex>
      </CardBody>
      <CardDivider />
      <CardBody>
        <ToggleControl
          label={ __( 'Enable "Public" Future Events', 'invintus' ) }
          checked={ enablePublicEvents }
          onChange={ ( value ) => setEnablePublicEvents( value ) }
          help={ __( 'When enabled, future events marked as "Public" in Invintus Control Center will be displayed. When disabled, only past events and live events will be shown.', 'invintus' ) }
        />
      </CardBody>
      <CardDivider />
      <CardBody>
        <Flex className="invintus-app-settings-watch-current-url" direction="column" gap={ 2 }>
          <TextControl
            label={ __( 'Watch URL', 'invintus' ) }
            value={ window?.invintusConfig?.playerUrl || '' }
            readOnly
            help={ __( 'This is the URL where your videos will be displayed.', 'invintus' ) }
          />
          <Flex justify="end">
            <Button
              variant="secondary"
              onClick={ () => {
                navigator.clipboard.writeText( window?.invintusConfig?.playerUrl || '' );
              }}
            >
              <Flex gap={ 1 } align="center">
                <Dashicon icon="admin-page" />
                { __( 'Copy URL', 'invintus' ) }
              </Flex>
            </Button>
          </Flex>
        </Flex>
      </CardBody>
    </>
  );
};

export default SectionGeneral;
