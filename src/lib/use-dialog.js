/**
 * Accessibility behaviour shared by the CRM's modal slide-overs.
 *
 * Returns a ref to place on the dialog panel. While the dialog is mounted it:
 *   - moves focus into the panel,
 *   - keeps Tab / Shift+Tab cycling within the panel (focus trap),
 *   - closes on Escape,
 *   - restores focus to the element that opened it on unmount.
 *
 * @param {Function} onClose Called when the user presses Escape.
 * @return {Object} A ref to attach to the dialog's panel element.
 */
import { useEffect, useRef } from '@wordpress/element';

const FOCUSABLE = [
	'a[href]',
	'button:not([disabled])',
	'input:not([disabled])',
	'select:not([disabled])',
	'textarea:not([disabled])',
	'[tabindex]:not([tabindex="-1"])',
].join( ',' );

export default function useDialog( onClose ) {
	const ref = useRef( null );
	const closeRef = useRef( onClose );
	closeRef.current = onClose;

	useEffect( () => {
		const node = ref.current;
		const previouslyFocused = typeof document !== 'undefined' ? document.activeElement : null;

		const focusable = () => {
			if ( ! node ) {
				return [];
			}
			return Array.prototype.slice
				.call( node.querySelectorAll( FOCUSABLE ) )
				.filter( ( el ) => el.offsetParent !== null || el === document.activeElement );
		};

		// Move focus into the dialog.
		const first = focusable()[ 0 ];
		if ( first ) {
			first.focus();
		} else if ( node ) {
			node.setAttribute( 'tabindex', '-1' );
			node.focus();
		}

		const onKeyDown = ( e ) => {
			if ( e.key === 'Escape' ) {
				e.preventDefault();
				if ( closeRef.current ) {
					closeRef.current();
				}
				return;
			}

			if ( e.key === 'Tab' && node ) {
				const items = focusable();
				if ( ! items.length ) {
					return;
				}
				const firstEl = items[ 0 ];
				const lastEl = items[ items.length - 1 ];
				const active = document.activeElement;

				if ( e.shiftKey ) {
					if ( active === firstEl || ! node.contains( active ) ) {
						e.preventDefault();
						lastEl.focus();
					}
				} else if ( active === lastEl || ! node.contains( active ) ) {
					e.preventDefault();
					firstEl.focus();
				}
			}
		};

		document.addEventListener( 'keydown', onKeyDown, true );

		return () => {
			document.removeEventListener( 'keydown', onKeyDown, true );
			if ( previouslyFocused && previouslyFocused.focus ) {
				previouslyFocused.focus();
			}
		};
	}, [] );

	return ref;
}
