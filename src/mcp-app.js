/**
 * MCP Server admin screen.
 */
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	ToggleControl,
	Button,
	Spinner,
	Notice,
	TextControl,
	ExternalLink,
} from '@wordpress/components';

const SETTINGS = 'wppdt/v1/mcp-admin/settings';
const TOKEN = 'wppdt/v1/mcp-admin/token';
const GROUPS = 'wppdt/v1/mcp-admin/groups';

function formatTime( ts ) {
	if ( ! ts ) {
		return '';
	}
	return new Date( ts * 1000 ).toLocaleString();
}

export default function McpApp() {
	const [ loading, setLoading ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ status, setStatus ] = useState( null );
	const [ newToken, setNewToken ] = useState( '' );
	const [ notice, setNotice ] = useState( null );

	const load = () => {
		apiFetch( { path: SETTINGS } )
			.then( setStatus )
			.catch( () =>
				setNotice( {
					status: 'error',
					message: __( 'Failed to load settings.', 'wp-pro-devs-toolkit' ),
				} )
			)
			.finally( () => setLoading( false ) );
	};

	useEffect( load, [] );

	const toggleActive = ( active ) => {
		setBusy( true );
		setNotice( null );
		apiFetch( { path: SETTINGS, method: 'POST', data: { active } } )
			.then( setStatus )
			.catch( () =>
				setNotice( {
					status: 'error',
					message: __( 'Could not update the server state.', 'wp-pro-devs-toolkit' ),
				} )
			)
			.finally( () => setBusy( false ) );
	};

	const toggleGroup = ( id, enabled ) => {
		setBusy( true );
		setNotice( null );
		apiFetch( { path: GROUPS, method: 'POST', data: { group: id, enabled } } )
			.then( setStatus )
			.catch( () =>
				setNotice( {
					status: 'error',
					message: __( 'Could not update the tool group.', 'wp-pro-devs-toolkit' ),
				} )
			)
			.finally( () => setBusy( false ) );
	};

	const generateToken = () => {
		setBusy( true );
		setNotice( null );
		apiFetch( { path: TOKEN, method: 'POST' } )
			.then( ( data ) => {
				setNewToken( data.token || '' );
				setStatus( data );
			} )
			.catch( () =>
				setNotice( {
					status: 'error',
					message: __( 'Could not generate a token.', 'wp-pro-devs-toolkit' ),
				} )
			)
			.finally( () => setBusy( false ) );
	};

	const revokeToken = () => {
		setBusy( true );
		setNotice( null );
		setNewToken( '' );
		apiFetch( { path: TOKEN, method: 'DELETE' } )
			.then( setStatus )
			.catch( () =>
				setNotice( {
					status: 'error',
					message: __( 'Could not revoke the token.', 'wp-pro-devs-toolkit' ),
				} )
			)
			.finally( () => setBusy( false ) );
	};

	if ( loading ) {
		return (
			<div className="wppdt-loading">
				<Spinner />
			</div>
		);
	}

	if ( ! status ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ __( 'Settings unavailable.', 'wp-pro-devs-toolkit' ) }
			</Notice>
		);
	}

	return (
		<div className="wppdt-admin wppdt-mcp">
			{ notice && (
				<Notice status={ notice.status } onRemove={ () => setNotice( null ) }>
					{ notice.message }
				</Notice>
			) }

			{ status.active && ! status.hasToken && (
				<Notice status="warning" isDismissible={ false }>
					{ __( 'The server is on but has no access token yet — generate one below before connecting a client.', 'wp-pro-devs-toolkit' ) }
				</Notice>
			) }

			<div className="wppdt-mcp-cols">
			<div className="wppdt-mcp-main">
			<Card>
				<CardHeader>
					<h2 className="wppdt-card-title">{ __( 'Server', 'wp-pro-devs-toolkit' ) }</h2>
				</CardHeader>
				<CardBody>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'MCP Server active', 'wp-pro-devs-toolkit' ) }
						help={
							status.active
								? __( 'The endpoint is live and accepting authenticated requests.', 'wp-pro-devs-toolkit' )
								: __( 'The endpoint rejects every request. Turn on when you are ready.', 'wp-pro-devs-toolkit' )
						}
						checked={ status.active }
						disabled={ busy }
						onChange={ toggleActive }
					/>
					<div className="wppdt-mcp-field">
						<TextControl
							__nextHasNoMarginBottom
							label={ __( 'Endpoint URL', 'wp-pro-devs-toolkit' ) }
							help={ __( 'Point your MCP client here, with the token as a Bearer header.', 'wp-pro-devs-toolkit' ) }
							value={ status.endpoint }
							readOnly
							onChange={ () => {} }
						/>
					</div>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					<h2 className="wppdt-card-title">{ __( 'Access token', 'wp-pro-devs-toolkit' ) }</h2>
				</CardHeader>
				<CardBody>
					{ newToken && (
						<Notice status="success" isDismissible={ false }>
							<strong>{ __( 'Copy this token now — it is shown only once:', 'wp-pro-devs-toolkit' ) }</strong>
							<code className="wppdt-mcp-token">{ newToken }</code>
						</Notice>
					) }

					{ status.hasToken ? (
						<p className="wppdt-empty">
							{ __( 'A token is active', 'wp-pro-devs-toolkit' ) }
							{ status.tokenLast4 ? ` (…${ status.tokenLast4 })` : '' }
							{ status.tokenCreated ? `, ${ __( 'created', 'wp-pro-devs-toolkit' ) } ${ formatTime( status.tokenCreated ) }` : '' }
							.
						</p>
					) : (
						<p className="wppdt-empty">{ __( 'No token yet.', 'wp-pro-devs-toolkit' ) }</p>
					) }

					<div className="wppdt-mcp-actions">
						<Button variant="primary" isBusy={ busy } disabled={ busy } onClick={ generateToken }>
							{ status.hasToken ? __( 'Regenerate token', 'wp-pro-devs-toolkit' ) : __( 'Generate token', 'wp-pro-devs-toolkit' ) }
						</Button>
						{ status.hasToken && (
							<Button variant="secondary" isDestructive isBusy={ busy } disabled={ busy } onClick={ revokeToken }>
								{ __( 'Revoke', 'wp-pro-devs-toolkit' ) }
							</Button>
						) }
					</div>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					<h2 className="wppdt-card-title">{ __( 'Tools', 'wp-pro-devs-toolkit' ) }</h2>
				</CardHeader>
				<CardBody>
					<p className="wppdt-empty">
						{ __( 'Turn tool groups on or off. A group whose plugin is not installed is disabled.', 'wp-pro-devs-toolkit' ) }
					</p>
					{ ( status.groups || [] ).map( ( group ) => (
						<div key={ group.id } className="wppdt-mcp-group">
							<ToggleControl
								__nextHasNoMarginBottom
								label={ group.label }
								help={
									group.available
										? `${ group.tools.length } ${ __( 'tools', 'wp-pro-devs-toolkit' ) }: ${ group.tools.join( ', ' ) }`
										: `${ __( 'Requires', 'wp-pro-devs-toolkit' ) } ${ group.requires } — ${ __( 'not active', 'wp-pro-devs-toolkit' ) }`
								}
								checked={ group.enabled }
								disabled={ busy || ! group.available }
								onChange={ ( value ) => toggleGroup( group.id, value ) }
							/>
						</div>
					) ) }
				</CardBody>
			</Card>

			</div>
			<div className="wppdt-mcp-side">
			<Card>
				<CardHeader>
					<h2 className="wppdt-card-title">{ __( 'Recent activity', 'wp-pro-devs-toolkit' ) }</h2>
				</CardHeader>
				<CardBody>
					{ ( status.audit || [] ).length === 0 ? (
						<p className="wppdt-empty">{ __( 'No tool calls yet.', 'wp-pro-devs-toolkit' ) }</p>
					) : (
						<ul className="wppdt-mcp-audit">
							{ status.audit.map( ( entry, i ) => (
								<li key={ i } className={ entry.ok ? 'is-ok' : 'is-error' }>
									<span className="wppdt-mcp-audit-tool"><code>{ entry.tool }</code></span>
									<span className="wppdt-mcp-audit-time">{ formatTime( entry.time ) }</span>
									{ entry.summary && <span className="wppdt-mcp-audit-summary">{ entry.summary }</span> }
								</li>
							) ) }
						</ul>
					) }
				</CardBody>
			</Card>
			</div>
			</div>

			<p className="wppdt-mcp-docs">
				<ExternalLink href="https://modelcontextprotocol.io">
					{ __( 'About the Model Context Protocol', 'wp-pro-devs-toolkit' ) }
				</ExternalLink>
			</p>
		</div>
	);
}
