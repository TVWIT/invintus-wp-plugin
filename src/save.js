import { useBlockProps } from '@wordpress/block-editor';

export default function Save({ attributes }) {
  const blockProps = useBlockProps.save({
    style: {
      height: '250px',
      width: '100%',
      background: '#e5e5e5',
    },
  });

  return (
    <div { ...blockProps }>
      {/* Save output that matches your frontend needs */}
    </div>
  );
}
