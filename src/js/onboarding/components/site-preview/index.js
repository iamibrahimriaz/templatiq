import { memo, useEffect, useState } from '@wordpress/element';
import { useStateValue } from '../../store/store';
import { addTrailingSlash } from '../../utils/add-trailing-slash';
import { sendPostMessage } from '../../utils/functions';
import { prependHTTPS } from '../../utils/prepend-https';
import { stripSlashes } from '../../utils/strip-slashes';
import SiteSkeleton from './site-skeleton';

const SitePreview = () => {
	const [ { templateResponse, siteLogo } ] = useStateValue();
	const [ previewUrl, setPreviewUrl ] = useState( '' );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		const url = templateResponse
			? templateResponse[ 'templatiq-site-url' ]
			: '';

		if ( url !== '' ) { 
			setPreviewUrl(
				addTrailingSlash( prependHTTPS( stripSlashes( url ) ) )
			);
		}
	}, [ templateResponse ] );

	useEffect( () => {
		if ( loading !== false ) {
			return;
		}

		sendPostMessage( {
			param: 'cleanStorage',
			data: siteLogo,
		} );
	}, [ loading ] );

	const handleIframeLoading = () => {
		setLoading( false );
	};

	return (
		<>
			{ loading ? <SiteSkeleton /> : null }
			{ previewUrl !== '' && (
				<iframe
					id="astra-starter-templates-preview"
					title="Website Preview"
					height="100%"
					width="100%"
					src={ previewUrl }
					onLoad={ handleIframeLoading }
				/>
			) }
		</>
	);
};

export default memo( SitePreview );
