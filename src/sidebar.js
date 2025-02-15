import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { InvintusIconFullColor } from './components/InvintusIcon'; // Adjust the import path if necessary
import { __ } from '@wordpress/i18n';

import './sidebar.scss';

const InvintusSidebar = () => {
  const meta = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' ), [] );

  return (
    <>
      <PluginSidebarMoreMenuItem target="invintus-sidebar">
        { __( 'Invintus Metadata', 'invintus' ) }
      </PluginSidebarMoreMenuItem>
      <PluginSidebar
        name="invintus-sidebar"
        icon={<InvintusIconFullColor />}
        title="Invintus Metadata"
      >
        <div className="invintus-sidebar-content">
          <TextControl
            label="Event ID"
            value={meta.invintus_event_id || ''}
            readOnly
          />
          <TextControl
            label="Custom ID"
            value={meta.invintus_custom_id || ''}
            readOnly
          />
          <TextControl
            label="Caption"
            value={meta.invintus_caption || ''}
            readOnly
          />
          <TextControl
            label="Audio"
            value={meta.invintus_audio || ''}
            readOnly
          />
          <TextControl
            label="Location"
            value={meta.invintus_location || ''}
            readOnly
          />
          <TextControl
            label="Total Runtime"
            value={meta.invintus_total_runtime || ''}
            readOnly
          />
          <div>
            <div className="label">Thumbnail</div>
            <img src={meta.invintus_thumbnail || ''} alt="Thumbnail" />
          </div>
        </div>
      </PluginSidebar>
    </>
  );
};

registerPlugin( 'invintus-sidebar', {
  render: InvintusSidebar,
} );
