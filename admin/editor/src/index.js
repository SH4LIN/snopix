/**
 * Editor extension: adds a Snopix Search panel to the core/shortcode block
 * inspector whenever the block body contains a [snopix_search] tag (or
 * offers a one-click insert when it does not).
 *
 * The panel parses the shortcode attributes, exposes UI for the supported
 * options (variant, title, max_results), and writes the result back into the
 * block `text` attribute. Editing falls back gracefully if the block also
 * contains other shortcodes or surrounding text — only the [snopix_search]
 * tag is rewritten.
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	RangeControl,
	Button,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const TARGET_BLOCK = 'core/shortcode';
const SHORTCODE = 'snopix_search';

const DEFAULTS = Object.freeze( {
	variant: 'card',
	title: 'Search by image',
	max_results: 12,
} );

const VARIANTS = [
	{ value: 'card', label: __( 'Card', 'snopix' ) },
	{ value: 'inline', label: __( 'Inline', 'snopix' ) },
	{ value: 'narrow', label: __( 'Narrow', 'snopix' ) },
];

const SHORTCODE_RE = new RegExp( `\\[${ SHORTCODE }(\\s[^\\]]*)?\\]`, 'i' );
const ATTR_RE = /(\w+)\s*=\s*(?:"([^"]*)"|'([^']*)'|(\S+))/g;

function parseShortcode( text ) {
	const match = ( text || '' ).match( SHORTCODE_RE );
	if ( ! match ) {
		return null;
	}

	const attrs = {};
	const body = match[ 1 ] || '';
	let m;
	while ( ( m = ATTR_RE.exec( body ) ) !== null ) {
		const key = m[ 1 ].toLowerCase();
		const value = m[ 2 ] ?? m[ 3 ] ?? m[ 4 ] ?? '';
		attrs[ key ] = value;
	}
	ATTR_RE.lastIndex = 0;

	return { match: match[ 0 ], attrs };
}

function buildShortcode( attrs ) {
	const parts = [ SHORTCODE ];
	Object.entries( attrs ).forEach( ( [ key, value ] ) => {
		if ( value === '' || value === null || value === undefined ) {
			return;
		}
		const safe = String( value ).replace( /"/g, '&quot;' );
		parts.push( `${ key }="${ safe }"` );
	} );
	return `[${ parts.join( ' ' ) }]`;
}

function replaceOrAppend( text, nextShortcode ) {
	if ( SHORTCODE_RE.test( text || '' ) ) {
		return text.replace( SHORTCODE_RE, nextShortcode );
	}
	return text ? `${ text }\n${ nextShortcode }` : nextShortcode;
}

function clampMaxResults( raw ) {
	const n = parseInt( raw, 10 );
	if ( Number.isNaN( n ) ) {
		return DEFAULTS.max_results;
	}
	return Math.max( 1, Math.min( 48, n ) );
}

const withSnopixPanel = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		if ( props.name !== TARGET_BLOCK ) {
			return <BlockEdit { ...props } />;
		}

		const text = props.attributes.text || '';
		const parsed = parseShortcode( text );

		const update = ( key, value ) => {
			const current = parsed?.attrs ?? {};
			const next = { ...current, [ key ]: value };
			props.setAttributes( {
				text: replaceOrAppend( text, buildShortcode( next ) ),
			} );
		};

		const insert = () => {
			props.setAttributes( {
				text: replaceOrAppend( text, buildShortcode( DEFAULTS ) ),
			} );
		};

		const remove = () => {
			props.setAttributes( {
				text: ( text || '' ).replace( SHORTCODE_RE, '' ).trim(),
			} );
		};

		const attrs = parsed?.attrs ?? {};

		return (
			<Fragment>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __( 'Snopix Search', 'snopix' ) }
						initialOpen={ true }
					>
						{ ! parsed && (
							<Button variant="secondary" onClick={ insert }>
								{ __( 'Insert [snopix_search]', 'snopix' ) }
							</Button>
						) }
						{ parsed && (
							<Fragment>
								<SelectControl
									label={ __( 'Variant', 'snopix' ) }
									value={ attrs.variant || DEFAULTS.variant }
									options={ VARIANTS }
									onChange={ ( v ) => update( 'variant', v ) }
									help={ __(
										'Card has a full header; Inline is chrome-less; Narrow uses a denser results grid.',
										'snopix'
									) }
								/>
								<TextControl
									label={ __( 'Title', 'snopix' ) }
									value={ attrs.title ?? '' }
									onChange={ ( v ) => update( 'title', v ) }
									help={ __(
										'Header label. Ignored by the Inline variant.',
										'snopix'
									) }
								/>
								<RangeControl
									label={ __( 'Max results', 'snopix' ) }
									value={ clampMaxResults(
										attrs.max_results ?? DEFAULTS.max_results
									) }
									onChange={ ( v ) =>
										update( 'max_results', clampMaxResults( v ) )
									}
									min={ 1 }
									max={ 48 }
								/>
								<Button
									variant="tertiary"
									isDestructive
									onClick={ remove }
									style={ { marginTop: '8px' } }
								>
									{ __( 'Remove [snopix_search]', 'snopix' ) }
								</Button>
							</Fragment>
						) }
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	};
}, 'withSnopixPanel' );

addFilter( 'editor.BlockEdit', 'snopix/shortcode-panel', withSnopixPanel );
