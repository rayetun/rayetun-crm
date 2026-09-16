/**
 * Slide-over panel for viewing, creating and editing a contact, with a details
 * form and an activity timeline (notes + system events).
 */
import { useState, useEffect } from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';
import { request, fetchCollection } from '../lib/api';
import useDialog from '../lib/use-dialog';

const STATUSES = [ 'lead', 'prospect', 'client', 'inactive', 'archived' ];

const HEAT_LABELS = {
	cold: __( 'Cold', 'rayetun-crm' ),
	warm: __( 'Warm', 'rayetun-crm' ),
	hot: __( 'Hot', 'rayetun-crm' ),
	very_hot: __( 'Very hot', 'rayetun-crm' ),
};

/**
 * Whole days between an ISO-ish datetime and now (never negative).
 *
 * @param {string} iso A 'YYYY-MM-DD HH:MM:SS' or ISO datetime string.
 * @return {number} Days elapsed.
 */
function daysSince( iso ) {
	if ( ! iso ) {
		return 0;
	}
	const then = new Date( String( iso ).replace( ' ', 'T' ) ).getTime();
	if ( isNaN( then ) ) {
		return 0;
	}
	return Math.max( 0, Math.floor( ( Date.now() - then ) / 86400000 ) );
}

const BLANK = {
	first_name: '',
	last_name: '',
	email: '',
	phone: '',
	company: '',
	website: '',
	status: 'lead',
	source: '',
	star: false,
	tags: [],
	custom: {},
};

function toForm( contact ) {
	if ( ! contact ) {
		return { ...BLANK, custom: {} };
	}

	return {
		first_name: contact.first_name || '',
		last_name: contact.last_name || '',
		email: contact.email || '',
		phone: contact.phone || '',
		company: contact.company || '',
		website: contact.website || '',
		status: contact.status || 'lead',
		source: contact.source || '',
		star: !! contact.star,
		tags: ( contact.tags || [] ).map( ( t ) => t.name ),
		custom: { ...( contact.custom || {} ) },
	};
}

/**
 * Renders one custom field input bound to form.custom[field_key].
 *
 * @param {Object}   field    Field definition.
 * @param {string}   value    Current value.
 * @param {Function} onChange Change handler receiving the new value.
 * @return {JSX.Element} The input control.
 */
function CustomFieldInput( { field, value, onChange } ) {
	if ( field.type === 'checkbox' ) {
		return (
			<label className="rtcrm-check">
				<input
					type="checkbox"
					checked={ value === '1' || value === true }
					onChange={ ( e ) => onChange( e.target.checked ? '1' : '0' ) }
				/>
				<span>{ field.label }</span>
			</label>
		);
	}

	return (
		<label className="rtcrm-field">
			<span>{ field.label }</span>
			{ field.type === 'select' ? (
				<select value={ value || '' } onChange={ ( e ) => onChange( e.target.value ) }>
					<option value="">{ '—' }</option>
					{ field.options.map( ( o ) => (
						<option key={ o } value={ o }>{ o }</option>
					) ) }
				</select>
			) : (
				<input
					type={ field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : field.type === 'url' ? 'url' : 'text' }
					value={ value || '' }
					onChange={ ( e ) => onChange( e.target.value ) }
				/>
			) }
		</label>
	);
}

function cap( s ) {
	return s ? s.charAt( 0 ).toUpperCase() + s.slice( 1 ) : s;
}

function activityText( a ) {
	if ( a.type === 'note' ) {
		return a.data.content || '';
	}
	if ( a.type === 'status_changed' ) {
		return sprintf(
			/* translators: 1: old status, 2: new status. */
			__( 'Status changed from %1$s to %2$s', 'rayetun-crm' ),
			cap( a.data.from ),
			cap( a.data.to )
		);
	}
	if ( a.type === 'created' ) {
		return __( 'Contact created', 'rayetun-crm' );
	}
	if ( a.type === 'form_submission' ) {
		const form = a.data.form || __( 'a form', 'rayetun-crm' );
		const base = sprintf(
			/* translators: %s: form name. */
			__( 'Submitted %s', 'rayetun-crm' ),
			form
		);
		return a.data.message ? `${ base }: “${ a.data.message }”` : base;
	}
	if ( a.type === 'email' ) {
		const base = sprintf(
			/* translators: %s: email subject. */
			__( 'Emailed: %s', 'rayetun-crm' ),
			a.data.subject || ''
		);
		return a.data.status === 'failed' ? `${ base } (${ __( 'failed', 'rayetun-crm' ) })` : base;
	}
	if ( a.type === 'order' ) {
		return sprintf(
			/* translators: 1: order number, 2: currency symbol, 3: total. */
			__( 'Placed order #%1$s — %2$s%3$s', 'rayetun-crm' ),
			a.data.number,
			a.data.currency || '',
			a.data.total
		);
	}
	return a.type;
}

export default function ContactPanel( { contact, canEdit, canManage = false, canEmail = true, fields = [], onClose, onSaved, onDeleted } ) {
	const isNew = ! contact || ! contact.id;
	const dialogRef = useDialog( onClose );
	const [ tab, setTab ] = useState( 'details' );
	const [ form, setForm ] = useState( toForm( contact ) );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );

	const [ activities, setActivities ] = useState( [] );
	const [ loadingActivity, setLoadingActivity ] = useState( false );
	const [ note, setNote ] = useState( '' );
	const [ postingNote, setPostingNote ] = useState( false );

	const [ templates, setTemplates ] = useState( [] );
	const [ compose, setCompose ] = useState( false );
	const [ emailSubject, setEmailSubject ] = useState( '' );
	const [ emailBody, setEmailBody ] = useState( '' );
	const [ sendingEmail, setSendingEmail ] = useState( false );
	const [ woo, setWoo ] = useState( null );
	const [ subStatus, setSubStatus ] = useState( contact && contact.email_status ? contact.email_status : 'subscribed' );
	const [ subSaving, setSubSaving ] = useState( false );

	useEffect( () => {
		setForm( toForm( contact ) );
		setError( null );
		setTab( 'details' );
		setWoo( null );
		setSubStatus( contact && contact.email_status ? contact.email_status : 'subscribed' );
		if ( contact && contact.id ) {
			request( `contacts/${ contact.id }/woo`, 'GET' ).then( setWoo ).catch( () => setWoo( null ) );
		}
	}, [ contact ] );

	const loadActivities = () => {
		if ( isNew ) {
			return;
		}
		setLoadingActivity( true );
		fetchCollection( `contacts/${ contact.id }/activities` )
			.then( ( res ) => setActivities( res.items ) )
			.catch( () => setActivities( [] ) )
			.finally( () => setLoadingActivity( false ) );
	};

	useEffect( () => {
		if ( tab === 'activity' ) {
			loadActivities();
			request( 'email-templates', 'GET' )
				.then( ( tpls ) => setTemplates( Array.isArray( tpls ) ? tpls : [] ) )
				.catch( () => setTemplates( [] ) );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ tab ] );

	const applyTemplate = ( id ) => {
		const t = templates.find( ( x ) => x.id === id );
		if ( t ) {
			setEmailSubject( t.subject );
			setEmailBody( t.body );
		}
	};

	const sendEmail = async () => {
		if ( ! emailSubject.trim() || ! emailBody.trim() ) {
			return;
		}
		setSendingEmail( true );
		setError( null );
		try {
			await request( `contacts/${ contact.id }/email`, 'POST', { subject: emailSubject, body: emailBody } );
			setCompose( false );
			setEmailSubject( '' );
			setEmailBody( '' );
			loadActivities();
		} catch ( err ) {
			setError( err.message || __( 'Could not send the email.', 'rayetun-crm' ) );
		} finally {
			setSendingEmail( false );
		}
	};

	// Flip email consent. For a saved contact this persists immediately (like a
	// CRM subscription switch); for a new one it is remembered and written on save.
	const toggleSubscription = async () => {
		if ( ! canEdit || subSaving ) {
			return;
		}
		const next = subStatus === 'subscribed' ? 'unsubscribed' : 'subscribed';
		const previous = subStatus;
		setSubStatus( next );

		if ( isNew ) {
			return;
		}

		setSubSaving( true );
		try {
			await request( `contacts/${ contact.id }`, 'PUT', { email_status: next } );
		} catch ( err ) {
			setSubStatus( previous );
			setError( err.message || __( 'Could not update the subscription.', 'rayetun-crm' ) );
		} finally {
			setSubSaving( false );
		}
	};

	const set = ( key ) => ( e ) => {
		const value = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
		setForm( ( prev ) => ( { ...prev, [ key ]: value } ) );
	};

	const setCustom = ( key, value ) =>
		setForm( ( prev ) => ( { ...prev, custom: { ...prev.custom, [ key ]: value } } ) );

	const save = async () => {
		setSaving( true );
		setError( null );

		const body = {
			...form,
			email_status: subStatus,
			tags: ( '' + ( Array.isArray( form.tags ) ? form.tags.join( ',' ) : form.tags ) )
				.split( ',' )
				.map( ( t ) => t.trim() )
				.filter( Boolean ),
			custom: form.custom,
		};

		try {
			const saved = isNew
				? await request( 'contacts', 'POST', body )
				: await request( `contacts/${ contact.id }`, 'PUT', body );
			onSaved( saved );
		} catch ( err ) {
			setError( err.message || __( 'Could not save the contact.', 'rayetun-crm' ) );
		} finally {
			setSaving( false );
		}
	};

	const remove = async () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Delete this contact? This cannot be undone.', 'rayetun-crm' ) ) ) {
			return;
		}
		setSaving( true );
		try {
			await request( `contacts/${ contact.id }`, 'DELETE' );
			onDeleted( contact.id );
		} catch ( err ) {
			setError( err.message || __( 'Could not delete the contact.', 'rayetun-crm' ) );
			setSaving( false );
		}
	};

	const addNote = async () => {
		if ( ! note.trim() ) {
			return;
		}
		setPostingNote( true );
		try {
			const items = await request( `contacts/${ contact.id }/notes`, 'POST', { content: note } );
			setActivities( items );
			setNote( '' );
		} catch ( err ) {
			setError( err.message || __( 'Could not add the note.', 'rayetun-crm' ) );
		} finally {
			setPostingNote( false );
		}
	};

	const tagsValue = Array.isArray( form.tags ) ? form.tags.join( ', ' ) : form.tags;

	return (
		<div className="rtcrm-slideover" role="dialog" aria-modal="true" aria-labelledby="rtcrm-contact-title">
			<div className="rtcrm-slideover__backdrop" onClick={ onClose } />
			<div className="rtcrm-slideover__panel" ref={ dialogRef }>
				<header className="rtcrm-slideover__head">
					<h2 id="rtcrm-contact-title">
						{ isNew
							? __( 'New contact', 'rayetun-crm' )
							: form.first_name || form.last_name
								? `${ form.first_name } ${ form.last_name }`.trim()
								: __( 'Edit contact', 'rayetun-crm' ) }
					</h2>
					<button className="rtcrm-iconbtn" onClick={ onClose } aria-label={ __( 'Close', 'rayetun-crm' ) }>
						×
					</button>
				</header>

				{ ! isNew && (
					<div className="rtcrm-tabs">
						<button
							className={ 'rtcrm-tab' + ( tab === 'details' ? ' is-active' : '' ) }
							onClick={ () => setTab( 'details' ) }
						>
							{ __( 'Details', 'rayetun-crm' ) }
						</button>
						<button
							className={ 'rtcrm-tab' + ( tab === 'activity' ? ' is-active' : '' ) }
							onClick={ () => setTab( 'activity' ) }
						>
							{ __( 'Activity', 'rayetun-crm' ) }
						</button>
					</div>
				) }

				<div className="rtcrm-slideover__body">
					{ error && <div className="rtcrm-inline-error" role="alert">{ error }</div> }

					{ tab === 'details' && (
						<>
							{ ! isNew && contact && (
								<div className="rtcrm-chero">
									<div className="rtcrm-chero__tiles">
										<div className="rtcrm-ctile">
											<span className="rtcrm-ctile__label">{ __( 'Lead score', 'rayetun-crm' ) }</span>
											<span className="rtcrm-ctile__value">
												<span className={ `rtcrm-heat rtcrm-heat--${ contact.heat }` } />
												{ contact.lead_score }
											</span>
											<span className="rtcrm-ctile__sub">{ HEAT_LABELS[ contact.heat ] || '' }</span>
										</div>
										<div className="rtcrm-ctile">
											<span className="rtcrm-ctile__label">{ __( 'Status', 'rayetun-crm' ) }</span>
											<span className="rtcrm-ctile__value">
												<span className={ `rtcrm-badge rtcrm-badge--${ contact.status }` }>{ contact.status }</span>
											</span>
											<span className="rtcrm-ctile__sub">
												{ contact.tags && contact.tags.length
													? sprintf(
														/* translators: %d: number of tags. */
														_n( '%d tag', '%d tags', contact.tags.length, 'rayetun-crm' ),
														contact.tags.length
													)
													: __( 'No tags', 'rayetun-crm' ) }
											</span>
										</div>
										{ woo && woo.available && woo.order_count > 0 ? (
											<div className="rtcrm-ctile">
												<span className="rtcrm-ctile__label">{ __( 'Lifetime value', 'rayetun-crm' ) }</span>
												<span className="rtcrm-ctile__value">
													{ woo.currency }
													{ Number( woo.total_spent ).toLocaleString( undefined, { maximumFractionDigits: 0 } ) }
												</span>
												<span className="rtcrm-ctile__sub">
													{ sprintf(
														/* translators: %d: number of orders. */
														_n( '%d order', '%d orders', woo.order_count, 'rayetun-crm' ),
														woo.order_count
													) }
												</span>
											</div>
										) : (
											<div className="rtcrm-ctile">
												<span className="rtcrm-ctile__label">{ __( 'Contact age', 'rayetun-crm' ) }</span>
												<span className="rtcrm-ctile__value">{ daysSince( contact.created ) }</span>
												<span className="rtcrm-ctile__sub">
													{ sprintf(
														/* translators: %d: number of days. */
														_n( 'day', 'days', daysSince( contact.created ), 'rayetun-crm' ),
														daysSince( contact.created )
													) }
												</span>
											</div>
										) }
									</div>

									<button
										type="button"
										className={ 'rtcrm-subchip rtcrm-subchip--' + subStatus + ( canEdit ? '' : ' is-static' ) }
										onClick={ toggleSubscription }
										disabled={ ! canEdit || subSaving }
										aria-pressed={ subStatus === 'subscribed' }
										title={ canEdit ? __( 'Toggle email subscription', 'rayetun-crm' ) : undefined }
									>
										<span className="rtcrm-subchip__dot" />
										{ subStatus === 'subscribed'
											? __( 'Email: Subscribed', 'rayetun-crm' )
											: __( 'Email: Unsubscribed', 'rayetun-crm' ) }
									</button>
								</div>
							) }

							<div className="rtcrm-field-row">
								<label className="rtcrm-field">
									<span>{ __( 'First name', 'rayetun-crm' ) }</span>
									<input type="text" value={ form.first_name } onChange={ set( 'first_name' ) } />
								</label>
								<label className="rtcrm-field">
									<span>{ __( 'Last name', 'rayetun-crm' ) }</span>
									<input type="text" value={ form.last_name } onChange={ set( 'last_name' ) } />
								</label>
							</div>

							<label className="rtcrm-field">
								<span>{ __( 'Email', 'rayetun-crm' ) } *</span>
								<input type="email" value={ form.email } onChange={ set( 'email' ) } />
							</label>

							<div className="rtcrm-field-row">
								<label className="rtcrm-field">
									<span>{ __( 'Phone', 'rayetun-crm' ) }</span>
									<input type="text" value={ form.phone } onChange={ set( 'phone' ) } />
								</label>
								<label className="rtcrm-field">
									<span>{ __( 'Company', 'rayetun-crm' ) }</span>
									<input type="text" value={ form.company } onChange={ set( 'company' ) } />
								</label>
							</div>

							<label className="rtcrm-field">
								<span>{ __( 'Website', 'rayetun-crm' ) }</span>
								<input type="url" value={ form.website } onChange={ set( 'website' ) } />
							</label>

							<div className="rtcrm-field-row">
								<label className="rtcrm-field">
									<span>{ __( 'Status', 'rayetun-crm' ) }</span>
									<select value={ form.status } onChange={ set( 'status' ) }>
										{ STATUSES.map( ( s ) => (
											<option key={ s } value={ s }>{ cap( s ) }</option>
										) ) }
									</select>
								</label>
								<label className="rtcrm-field">
									<span>{ __( 'Source', 'rayetun-crm' ) }</span>
									<input type="text" value={ form.source } onChange={ set( 'source' ) } />
								</label>
							</div>

							<label className="rtcrm-field">
								<span>{ __( 'Tags', 'rayetun-crm' ) }</span>
								<input
									type="text"
									value={ tagsValue }
									onChange={ ( e ) => setForm( ( p ) => ( { ...p, tags: e.target.value } ) ) }
									placeholder={ __( 'Comma separated', 'rayetun-crm' ) }
								/>
							</label>

							{ fields.length > 0 && (
								<div className="rtcrm-customfields">
									<div className="rtcrm-fieldgroup-label">{ __( 'Custom fields', 'rayetun-crm' ) }</div>
									{ fields.map( ( f ) => (
										<CustomFieldInput
											key={ f.id }
											field={ f }
											value={ form.custom[ f.field_key ] }
											onChange={ ( v ) => setCustom( f.field_key, v ) }
										/>
									) ) }
								</div>
							) }

							{ woo && woo.available && woo.order_count > 0 && (
								<div className="rtcrm-purchases">
									<div className="rtcrm-fieldgroup-label">{ __( 'Purchases', 'rayetun-crm' ) }</div>
									<div className="rtcrm-purchases__summary">
										<span>
											{ sprintf(
												/* translators: %d: number of orders. */
												_n( '%d order', '%d orders', woo.order_count, 'rayetun-crm' ),
												woo.order_count
											) }
										</span>
										<strong>
											{ woo.currency }
											{ Number( woo.total_spent ).toLocaleString( undefined, { maximumFractionDigits: 0 } ) }
										</strong>
									</div>
									<ul className="rtcrm-purchases__list">
										{ woo.orders.map( ( o ) => (
											<li key={ o.number }>
												<span>#{ o.number }</span>
												<span className="rtcrm-sub">{ o.date }</span>
												<strong>{ woo.currency }{ o.total }</strong>
											</li>
										) ) }
									</ul>
								</div>
							) }

							<label className="rtcrm-check">
								<input type="checkbox" checked={ form.star } onChange={ set( 'star' ) } />
								<span>{ __( 'Star this contact', 'rayetun-crm' ) }</span>
							</label>
						</>
					) }

					{ tab === 'activity' && (
						<div className="rtcrm-activity">
							{ canEdit && canEmail && (
								<div className="rtcrm-emailbox">
									{ ! compose ? (
										<button className="rtcrm-btn rtcrm-btn--ghost" onClick={ () => setCompose( true ) }>
											{ __( '✉ Send email', 'rayetun-crm' ) }
										</button>
									) : (
										<div className="rtcrm-compose">
											{ templates.length > 0 && (
												<select defaultValue="" onChange={ ( e ) => applyTemplate( e.target.value ) }>
													<option value="">{ __( 'Use a template…', 'rayetun-crm' ) }</option>
													{ templates.map( ( t ) => (
														<option key={ t.id } value={ t.id }>{ t.name }</option>
													) ) }
												</select>
											) }
											<input
												type="text"
												placeholder={ __( 'Subject', 'rayetun-crm' ) }
												value={ emailSubject }
												onChange={ ( e ) => setEmailSubject( e.target.value ) }
											/>
											<textarea
												rows={ 5 }
												placeholder={ __( 'Write your message… {{first_name}} is supported', 'rayetun-crm' ) }
												value={ emailBody }
												onChange={ ( e ) => setEmailBody( e.target.value ) }
											/>
											<div className="rtcrm-compose__foot">
												<div className="rtcrm-spacer" />
												<button className="rtcrm-btn rtcrm-btn--ghost" onClick={ () => setCompose( false ) } disabled={ sendingEmail }>
													{ __( 'Cancel', 'rayetun-crm' ) }
												</button>
												<button
													className="rtcrm-btn rtcrm-btn--primary"
													onClick={ sendEmail }
													disabled={ sendingEmail || ! emailSubject.trim() || ! emailBody.trim() }
												>
													{ sendingEmail ? __( 'Sending…', 'rayetun-crm' ) : __( 'Send', 'rayetun-crm' ) }
												</button>
											</div>
										</div>
									) }
								</div>
							) }

							{ canEdit && (
								<div className="rtcrm-notebox">
									<textarea
										value={ note }
										onChange={ ( e ) => setNote( e.target.value ) }
										placeholder={ __( 'Add a note…', 'rayetun-crm' ) }
										rows={ 3 }
									/>
									<div className="rtcrm-notebox__foot">
										<button
											className="rtcrm-btn rtcrm-btn--primary"
											onClick={ addNote }
											disabled={ postingNote || ! note.trim() }
										>
											{ postingNote ? __( 'Adding…', 'rayetun-crm' ) : __( 'Add note', 'rayetun-crm' ) }
										</button>
									</div>
								</div>
							) }

							{ loadingActivity && <p className="rtcrm-sub">{ __( 'Loading…', 'rayetun-crm' ) }</p> }

							{ ! loadingActivity && activities.length === 0 && (
								<p className="rtcrm-sub">{ __( 'No activity yet.', 'rayetun-crm' ) }</p>
							) }

							<ul className="rtcrm-timeline">
								{ activities.map( ( a ) => (
									<li key={ a.id } className={ `rtcrm-tl rtcrm-tl--${ a.type }` }>
										<div className="rtcrm-tl__dot" />
										<div className="rtcrm-tl__body">
											<div className="rtcrm-tl__text">{ activityText( a ) }</div>
											<div className="rtcrm-tl__meta">
												{ a.author ? `${ a.author } · ` : '' }
												{ ( a.created || '' ).replace( 'T', ' ' ).slice( 0, 16 ) }
											</div>
										</div>
									</li>
								) ) }
							</ul>
						</div>
					) }
				</div>

				{ tab === 'details' && (
					<footer className="rtcrm-slideover__foot">
						{ ! isNew && canManage && (
							<button className="rtcrm-btn rtcrm-btn--danger" onClick={ remove } disabled={ saving }>
								{ __( 'Delete', 'rayetun-crm' ) }
							</button>
						) }
						<div className="rtcrm-spacer" />
						<button className="rtcrm-btn rtcrm-btn--ghost" onClick={ onClose } disabled={ saving }>
							{ __( 'Cancel', 'rayetun-crm' ) }
						</button>
						{ canEdit && (
							<button className="rtcrm-btn rtcrm-btn--primary" onClick={ save } disabled={ saving }>
								{ saving ? __( 'Saving…', 'rayetun-crm' ) : __( 'Save contact', 'rayetun-crm' ) }
							</button>
						) }
					</footer>
				) }
			</div>
		</div>
	);
}
