// WordPress dependencies
import { __ } from '@wordpress/i18n';
import {
  // Basic UI components
  TextControl,

  // Card components
  CardHeader,
  CardBody,

  // Layout components
  Flex,
  FlexBlock,

  // Other components
  __experimentalHeading as Heading,
  Button,
  Dashicon,
} from '@wordpress/components';

/**
 * Sanitizes a path by removing leading and trailing slashes and replacing multiple slashes with a single slash.
 * @param {string} path - The path to sanitize.
 * @return {string} The sanitized path.
 */
const sanitizePath = ( path ) => {
  return path.replace( /^\/+|\/+$/g, '' ).replace( /\/+/g, '/' );
};

const SectionRedirects = ( { watchRedirectPath, setWatchRedirectPath, handleWatchRedirectPathBlur, invintusConfig } ) => {
  return (
    <>
      <CardHeader isBorderless="true">
        <Heading level={4}>{ __( 'Redirects', 'invintus' ) }</Heading>
      </CardHeader>
      <CardBody>
        <Flex gap={ 4 } align="start">
          <FlexBlock>
            <TextControl
              label={ __( '"Watch" Redirect Path', 'invintus' ) }
              value={watchRedirectPath}
              onChange={setWatchRedirectPath}
              onBlur={handleWatchRedirectPathBlur}
              placeholder={invintusConfig.defaultWatchEndpoint}
              help={ __( 'Enter the redirect path for "Watch".', 'invintus' ) }
            />
          </FlexBlock>
          <FlexBlock className="invintus-app-settings-watch-current-url">
            <Flex direction="column" gap={ 2 }>
              <TextControl
                readOnly
                label={ __( 'Preview', 'invintus' ) }
                value={watchRedirectPath.trim() !== '' ? `${invintusConfig.siteUrl}/${sanitizePath( watchRedirectPath )}` : invintusConfig.defaultWatchUrl}
              />
              <Flex justify="end">
                <Button
                  variant="secondary"
                  onClick={ () => {
                    navigator.clipboard.writeText( watchRedirectPath.trim() !== '' ? `${invintusConfig.siteUrl}/${sanitizePath( watchRedirectPath )}` : invintusConfig.defaultWatchUrl );
                  }}
                >
                  <Flex gap={ 1 } align="center">
                    <Dashicon icon="admin-page" />
                    { __( 'Copy URL', 'invintus' ) }
                  </Flex>
                </Button>
              </Flex>
            </Flex>
          </FlexBlock>
        </Flex>
      </CardBody>
    </>
  );
};

export default SectionRedirects;
