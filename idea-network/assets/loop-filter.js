/**
 * Loop Grid filter widget behaviour: dependent city dropdown, auto submit and
 * the cookie that keeps the filter alive during Loop Grid AJAX pagination.
 *
 * @package excel-importer
 */

( function () {
	'use strict';

	var COOKIE_NAME = 'shabake_filter';
	var COOKIE_DAYS = 1;

	function parseJSON( value, fallback ) {
		try {
			return JSON.parse( value );
		} catch ( error ) {
			return fallback;
		}
	}

	function fieldValue( form, name ) {
		var field = form.querySelector( '[data-filter="' + name + '"]' );

		return field ? field.value : '';
	}

	function writeCookie( form ) {
		var data = {
			category: fieldValue( form, 'category' ),
			province: fieldValue( form, 'province' ),
			city: fieldValue( form, 'city' ),
			search: fieldValue( form, 'search' )
		};

		var expires = new Date( Date.now() + COOKIE_DAYS * 86400000 ).toUTCString();

		document.cookie = COOKIE_NAME + '=' + encodeURIComponent( JSON.stringify( data ) ) +
			'; path=/; expires=' + expires + '; SameSite=Lax';
	}

	function init( form ) {
		if ( ! form || form.getAttribute( 'data-shabake-ready' ) === '1' ) {
			return;
		}

		form.setAttribute( 'data-shabake-ready', '1' );

		var settings = parseJSON( form.getAttribute( 'data-settings' ) || '', {} );
		var province = form.querySelector( '[data-filter="province"]' );
		var city = form.querySelector( '[data-filter="city"]' );
		var search = form.querySelector( '[data-filter="search"]' );
		var cities = city ? parseJSON( city.getAttribute( 'data-cities' ) || '', [] ) : [];
		var cityAllLabel = city ? ( city.getAttribute( 'data-all-label' ) || '' ) : '';
		var cityNeedProvinceLabel = city ? ( city.getAttribute( 'data-need-province-label' ) || cityAllLabel ) : '';
		var requireProvince = !! settings.requireProvince;
		var submitTimer = null;
		var searchTimer = null;

		function submit() {
			writeCookie( form );

			if ( typeof form.requestSubmit === 'function' ) {
				form.requestSubmit();
			} else {
				form.submit();
			}
		}

		function scheduleSubmit() {
			if ( ! settings.autoSubmit ) {
				return;
			}

			window.clearTimeout( submitTimer );
			submitTimer = window.setTimeout( submit, 250 );
		}

		// The city is a child of a province: without a province there is no city
		// list at all, and with one only that province's cities are offered.
		function cityNeedsProvince() {
			return !! ( requireProvince && province && ! province.value );
		}

		function citiesForProvince() {
			if ( ! province || ! province.value ) {
				return requireProvince ? [] : cities;
			}

			if ( ! settings.citiesByProvince ) {
				return cities;
			}

			return cities.filter( function ( item ) {
				return item.province === province.value;
			} );
		}

		function fillCities( preserveSelection ) {
			if ( ! city ) {
				return;
			}

			var previous = preserveSelection ? city.value : '';
			var locked = cityNeedsProvince();
			var list = citiesForProvince();
			var fragment = document.createDocumentFragment();
			var placeholder = document.createElement( 'option' );

			placeholder.value = '';
			placeholder.textContent = locked ? cityNeedProvinceLabel : cityAllLabel;
			fragment.appendChild( placeholder );

			list.forEach( function ( item ) {
				var option = document.createElement( 'option' );

				option.value = item.slug;
				option.textContent = item.name;
				fragment.appendChild( option );
			} );

			city.innerHTML = '';
			city.appendChild( fragment );

			if ( ! locked && previous && list.some( function ( item ) { return item.slug === previous; } ) ) {
				city.value = previous;
			} else {
				city.value = '';
			}

			city.disabled = locked;
			city.setAttribute( 'aria-disabled', locked ? 'true' : 'false' );
			form.classList.toggle( 'shabake-loop-filter--city-locked', locked );
		}

		if ( province && city ) {
			province.addEventListener( 'change', function () {
				fillCities( false );
				scheduleSubmit();
			} );
		}

		if ( city ) {
			city.addEventListener( 'change', scheduleSubmit );
		}

		if ( search ) {
			search.addEventListener( 'input', function () {
				if ( ! settings.autoSubmit ) {
					return;
				}

				window.clearTimeout( searchTimer );
				searchTimer = window.setTimeout( submit, 700 );
			} );
		}

		form.addEventListener( 'submit', function () {
			writeCookie( form );
		} );

		fillCities( true );

		// Keep the cookie in sync with this page so that AJAX pagination of the
		// Loop Grid always re-runs the query with the active filter, and clear a
		// filter left over from another page.
		var hasFilter = [ 'category', 'province', 'city', 'search' ].some( function ( name ) {
			return '' !== fieldValue( form, name );
		} );

		if ( hasFilter ) {
			writeCookie( form );
		} else {
			document.cookie = COOKIE_NAME + '=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT';
		}
	}

	function boot( root ) {
		var scope = root || document;

		if ( ! scope.querySelectorAll ) {
			return;
		}

		Array.prototype.forEach.call( scope.querySelectorAll( '.shabake-loop-filter' ), init );
	}

	if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
		window.elementorFrontend.hooks.addAction(
			'frontend/element_ready/idea-network-loop-filter.default',
			function ( $scope ) {
				var element = $scope && $scope[ 0 ] ? $scope[ 0 ] : null;

				if ( ! element ) {
					return;
				}

				var form = element.classList.contains( 'shabake-loop-filter' )
					? element
					: element.querySelector( '.shabake-loop-filter' );

				init( form );
			}
		);
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () { boot(); } );
	} else {
		boot();
	}
}() );
