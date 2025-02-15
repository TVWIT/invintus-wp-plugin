// WordPress dependencies
import { __ } from '@wordpress/i18n';
import {
  // Basic UI components
  SelectControl,
  ToggleControl,

  // Card components
  CardHeader,
  CardBody,

  // Layout components
  Flex,
  FlexBlock,

  // Other components
  __experimentalHeading as Heading,
} from '@wordpress/components';

const SectionLogs = ( { enableLogs, setEnableLogs, logRetention, setLogRetention, logRetentionOptions } ) => {
  return (
    <>
      <CardHeader isBorderless="true">
        <Heading level={4}>{ __( 'Logs', 'invintus' ) }</Heading>
      </CardHeader>
      <CardBody>
        <Flex className="invintus-app-settings-fields__logs" gap="2" align="start">
          <FlexBlock>
            <ToggleControl
              label={ __( 'Log Payloads', 'invintus' ) }
              checked={enableLogs}
              onChange={setEnableLogs}
            />
          </FlexBlock>
          <FlexBlock>
            <SelectControl
              label={ __( 'Log Retention', 'invintus' ) }
              value={logRetention}
              options={logRetentionOptions}
              onChange={setLogRetention}
              help={ __( 'Select the log retention period.', 'invintus' ) }
            />
          </FlexBlock>
        </Flex>
      </CardBody>
    </>
  );
};

export default SectionLogs;
