/**
 * Main settings application.
 */
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	CardFooter,
	ToggleControl,
	Button,
	Spinner,
	Notice,
} from '@wordpress/components';

export default function App() {
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ modules, setModules ] = useState( [] );
	const [ enabled, setEnabled ] = useState( [] );
	const [ notice, setNotice ] = useState( null );

	useEffect( () => {
		apiFetch( { path: 'wppdt/v1/settings' } )
			.then( ( data ) => {
				setModules( data.modules || [] );
				setEnabled( data.enabled || [] );
			} )
			.catch( () =>
				setNotice( {
					status: 'error',
					message: __(
						'Failed to load settings.',
						'wp-pro-devs-toolkit'
					),
				} )
			)
			.finally( () => setLoading( false ) );
	}, [] );

	const toggle = ( id ) => {
		setEnabled( ( current ) =>
			current.includes( id )
				? current.filter( ( item ) => item !== id )
				: [ ...current, id ]
		);
	};

	const save = () => {
		setSaving( true );
		setNotice( null );

		apiFetch( {
			path: 'wppdt/v1/settings',
			method: 'POST',
			data: { enabled },
		} )
			.then( ( data ) => {
				setEnabled( data.enabled || [] );
				setNotice( {
					status: 'success',
					message: __(
						'Settings saved.',
						'wp-pro-devs-toolkit'
					),
				} );
			} )
			.catch( () =>
				setNotice( {
					status: 'error',
					message: __(
						'Failed to save settings.',
						'wp-pro-devs-toolkit'
					),
				} )
			)
			.finally( () => setSaving( false ) );
	};

	if ( loading ) {
		return (
			<div className="wppdt-loading">
				<Spinner />
			</div>
		);
	}

	return (
		<div className="wppdt-admin">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<Card>
				<CardHeader>
					<h2 className="wppdt-card-title">
						{ __( 'Features', 'wp-pro-devs-toolkit' ) }
					</h2>
				</CardHeader>

				<CardBody>
					{ modules.length === 0 ? (
						<p className="wppdt-empty">
							{ __(
								'No features are available yet. They will appear here as they are added to the plugin.',
								'wp-pro-devs-toolkit'
							) }
						</p>
					) : (
						modules.map( ( module ) => (
							<div
								key={ module.id }
								className="wppdt-module-row"
							>
								<ToggleControl
									__nextHasNoMarginBottom
									label={ module.label }
									help={ module.description }
									checked={ enabled.includes( module.id ) }
									onChange={ () => toggle( module.id ) }
								/>
							</div>
						) )
					) }
				</CardBody>

				{ modules.length > 0 && (
					<CardFooter>
						<Button
							variant="primary"
							isBusy={ saving }
							disabled={ saving }
							onClick={ save }
						>
							{ saving
								? __( 'Saving…', 'wp-pro-devs-toolkit' )
								: __(
										'Save Changes',
										'wp-pro-devs-toolkit'
								  ) }
						</Button>
					</CardFooter>
				) }
			</Card>
		</div>
	);
}
