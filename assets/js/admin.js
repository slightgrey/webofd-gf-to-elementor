/**
 * Web of D GF to Elementor: migrate and roll back from the admin screen.
 *
 * Migrating runs one short REST request per location (start, each embed, finalize),
 * so pages with many embeds never hit a timeout and progress is shown as it happens.
 *
 * @package GFToElementor
 */

( function () {
	'use strict';

	const { __, _n, sprintf } = wp.i18n;
	const settings = window.gf2elAdmin || {};

	/**
	 * Calls a plugin REST route.
	 *
	 * @param {string}      path Route under the plugin namespace, e.g. "/forms/3/preview".
	 * @param {Object|null} data POST body, or null for GET.
	 * @return {Promise<Object>} Response data; rejects with the error body.
	 */
	function api( path, data ) {
		return wp.apiFetch( {
			path: '/' + settings.restNamespace + path,
			method: null === data ? 'GET' : 'POST',
			data: null === data ? undefined : data,
		} );
	}

	/**
	 * Builds a status badge element.
	 *
	 * @param {string} status Status key.
	 * @param {string} label  Text.
	 * @return {HTMLElement} Badge.
	 */
	function badge( status, label ) {
		const span = document.createElement( 'span' );
		span.className = 'gf2el-badge gf2el-badge--' + status;
		span.textContent = label;
		return span;
	}

	/**
	 * Returns the label for an embed result.
	 *
	 * @param {string} status done, skipped or failed.
	 * @return {string} Label.
	 */
	function resultLabel( status ) {
		const labels = {
			done: __( 'Swapped', 'webofd-gf-to-elementor' ),
			skipped: __( 'Skipped', 'webofd-gf-to-elementor' ),
			failed: __( 'Failed', 'webofd-gf-to-elementor' ),
			working: __( 'Working…', 'webofd-gf-to-elementor' ),
		};
		return labels[ status ] || status;
	}

	/**
	 * Shows an embed's result in its table row.
	 *
	 * @param {HTMLFormElement} form   Migrate form.
	 * @param {string}          key    Embed key.
	 * @param {Object}          result Result with status and optional message.
	 */
	function markRow( form, key, result ) {
		const row = Array.from( form.querySelectorAll( 'tr[data-key]' ) ).find( ( tr ) => tr.dataset.key === key );
		const cell = row ? row.querySelector( '.gf2el-result' ) : null;

		if ( ! cell ) {
			return;
		}

		cell.replaceChildren( badge( result.status, resultLabel( result.status ) ) );

		if ( result.message ) {
			cell.append( ' ' + result.message );
		}
	}

	/**
	 * Adds a line to the progress box.
	 *
	 * @param {HTMLElement} box  Progress box.
	 * @param {string}      text Text.
	 * @return {HTMLElement} The new line.
	 */
	function log( box, text ) {
		const line = document.createElement( 'p' );
		line.textContent = text;
		box.append( line );
		return line;
	}

	/**
	 * Swaps one embed; a failed request becomes a "failed" result instead of stopping the run.
	 *
	 * @param {number} migrationId Migration ID.
	 * @param {string} key         Embed key.
	 * @return {Promise<Object>} Result.
	 */
	async function swapOne( migrationId, key ) {
		try {
			return await api( '/migrations/' + migrationId + '/embeds/' + key, {} );
		} catch ( error ) {
			return { status: 'failed', message: ( error && error.message ) || __( 'The request failed.', 'webofd-gf-to-elementor' ) };
		}
	}

	/**
	 * Runs the migration for the selected embeds.
	 *
	 * @param {HTMLFormElement} form Migrate form.
	 */
	async function migrate( form ) {
		const button = form.querySelector( 'button[type="submit"]' );
		const box = document.getElementById( 'gf2el-progress' );
		const keys = Array.from( form.querySelectorAll( 'input[name="embeds[]"]:checked' ) ).map( ( input ) => input.value );
		let question = __( 'No locations are selected. Convert the form into a saved Elementor template only?', 'webofd-gf-to-elementor' );

		if ( keys.length > 0 ) {
			question = sprintf(
				/* translators: %d: number of locations. */
				_n( 'Migrate this form and swap it in %d location? Each change is backed up and can be rolled back.', 'Migrate this form and swap it in %d locations? Each change is backed up and can be rolled back.', keys.length, 'webofd-gf-to-elementor' ),
				keys.length
			);
		}

		if ( ! window.confirm( question ) ) {
			return;
		}

		button.disabled = true;
		form.querySelectorAll( 'input[type="checkbox"]' ).forEach( ( input ) => ( input.disabled = true ) );
		box.hidden = false;
		box.replaceChildren();
		log( box, __( 'Converting the form…', 'webofd-gf-to-elementor' ) );

		let migrationId = 0;

		try {
			const start = await api( '/forms/' + form.dataset.form + '/migrate', { embeds: keys } );
			migrationId = start.migration_id;

			for ( const [ index, key ] of start.queue.entries() ) {
				markRow( form, key, { status: 'working' } );
				/* translators: 1: location number, 2: total locations. */
				const line = log( box, sprintf( __( 'Swapping location %1$d of %2$d…', 'webofd-gf-to-elementor' ), index + 1, start.queue.length ) );
				const result = await swapOne( migrationId, key );
				markRow( form, key, result );
				line.textContent += ' ' + resultLabel( result.status );
			}

			const done = await api( '/migrations/' + migrationId + '/finalize', {} );
			finish( box, done.report_url, done.status );
		} catch ( error ) {
			log( box, ( error && error.message ) || __( 'The migration stopped because of an error.', 'webofd-gf-to-elementor' ) ).className = 'gf2el-error';

			if ( migrationId ) {
				finish( box, settings.listUrl + '&action=report&migration=' + migrationId, 'running' );
			}
		}
	}

	/**
	 * Shows the final status and a link to the report.
	 *
	 * @param {HTMLElement} box       Progress box.
	 * @param {string}      reportUrl Report URL.
	 * @param {string}      status    Final status.
	 */
	function finish( box, reportUrl, status ) {
		const line = log( box, '' );
		const link = document.createElement( 'a' );
		const labels = {
			migrated: __( 'Migration finished.', 'webofd-gf-to-elementor' ),
			partial: __( 'Migration finished, but some locations failed.', 'webofd-gf-to-elementor' ),
			failed: __( 'Migration failed. Nothing was swapped.', 'webofd-gf-to-elementor' ),
			running: __( 'The migration did not finish.', 'webofd-gf-to-elementor' ),
		};

		link.href = reportUrl;
		link.className = 'button button-primary';
		link.textContent = __( 'View the report', 'webofd-gf-to-elementor' );
		line.append( badge( status, labels[ status ] || status ), ' ', link );
	}

	/**
	 * Rolls back a migration, asking again when pages were edited after it.
	 *
	 * @param {HTMLElement} button Roll back button or link.
	 * @param {boolean}     force  Overwrite later edits.
	 */
	async function rollback( button, force ) {
		button.setAttribute( 'aria-disabled', 'true' );
		button.classList.add( 'disabled' );

		try {
			await api( '/migrations/' + button.dataset.migration + '/rollback', { force } );
			window.location.reload();
		} catch ( error ) {
			button.removeAttribute( 'aria-disabled' );
			button.classList.remove( 'disabled' );

			if ( error && 'conflicts' === error.status && confirmOverwrite( error.conflicts ) ) {
				rollback( button, true );
				return;
			}

			if ( ! error || 'conflicts' !== error.status ) {
				window.alert( ( error && error.message ) || __( 'The rollback failed.', 'webofd-gf-to-elementor' ) );
			}
		}
	}

	/**
	 * Asks whether to overwrite locations edited after the migration.
	 *
	 * @param {Array<Object>} conflicts Conflicts with titles.
	 * @return {boolean} True to overwrite.
	 */
	function confirmOverwrite( conflicts ) {
		const titles = Array.from( new Set( ( conflicts || [] ).map( ( conflict ) => '• ' + conflict.title ) ) ).join( '\n' );

		return window.confirm(
			__( 'These were edited after the migration:', 'webofd-gf-to-elementor' ) + '\n' + titles + '\n\n' +
			__( 'Restore them anyway? The later edits will be lost.', 'webofd-gf-to-elementor' )
		);
	}

	document.addEventListener( 'change', ( event ) => {
		if ( event.target.classList.contains( 'gf2el-select-all' ) ) {
			event.target.closest( 'table' ).querySelectorAll( 'input[name="embeds[]"]' ).forEach( ( input ) => ( input.checked = event.target.checked ) );
		}
	} );

	document.addEventListener( 'submit', ( event ) => {
		if ( 'gf2el-migrate' === event.target.id ) {
			event.preventDefault();
			migrate( event.target );
		}
	} );

	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '.gf2el-rollback' );

		if ( ! button || 'true' === button.getAttribute( 'aria-disabled' ) ) {
			return;
		}

		event.preventDefault();

		if ( window.confirm( __( 'Roll back this migration? Every page and widget it changed is restored to how it was before.', 'webofd-gf-to-elementor' ) ) ) {
			rollback( button, false );
		}
	} );
}() );
