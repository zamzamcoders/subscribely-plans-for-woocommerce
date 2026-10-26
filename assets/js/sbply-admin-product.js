/**
 * Sbply admin — plan rows in the product "Subscription" tab.
 * Vanilla JS: add a row from the template, remove a row.
 */
( function () {
	'use strict';

	function init() {
		var panel = document.getElementById( 'sbply_product_data' );
		var template = document.getElementById( 'tmpl-sbply-plan-row' );

		if ( ! panel || ! template ) {
			return;
		}

		var rows = panel.querySelector( '.sbply-plans-table__rows' );
		var empty = panel.querySelector( '.sbply-plans-editor__empty' );
		var counter = rows.children.length;

		function refreshEmpty() {
			empty.hidden = rows.children.length > 0;
		}

		panel.addEventListener( 'click', function ( event ) {
			var add = event.target.closest( '.sbply-add-plan' );
			var remove = event.target.closest( '.sbply-remove-plan' );

			if ( add ) {
				event.preventDefault();

				// Unique index per row so PHP receives separate sbply_plans[n] arrays.
				var html = template.innerHTML.replace( /__INDEX__/g, 'new' + counter++ );
				rows.insertAdjacentHTML( 'beforeend', html );
				refreshEmpty();

				var input = rows.lastElementChild && rows.lastElementChild.querySelector( 'input[type="text"]' );
				if ( input ) {
					input.focus();
				}
			}

			if ( remove ) {
				event.preventDefault();
				remove.closest( 'tr' ).remove();
				refreshEmpty();
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
