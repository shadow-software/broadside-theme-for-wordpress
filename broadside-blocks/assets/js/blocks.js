/**
 * Broadside — block editor registration.
 *
 * Written against the wp.* globals rather than JSX and a bundler, on purpose:
 * the file a reviewer reads is the file the browser runs. There is no build
 * step, no minified vendor blob, and nothing in the plugin that cannot be
 * audited by reading it.
 *
 * Every block here is registered with a `save` of null — they are dynamic blocks
 * rendered in PHP (see inc-blocks.php). What is saved to the database is the
 * attributes and an HTML comment, so if the theme is ever removed the content
 * survives as data rather than as broken markup.
 *
 * WordPress 7 does not put a PHP-only block into the editor. This file has to
 * call registerBlockType for every block, including the ones that only preview
 * through ServerSideRender. It is enqueued once from broadside-blocks.php.
 *
 * @package Broadside_Blocks
 * @since   1.0.0
 */

( function ( blocks, element, blockEditor, components, i18n, serverSideRender, apiFetch ) {
	'use strict';

	var el = element.createElement;

	if ( serverSideRender && serverSideRender.default ) {
		serverSideRender = serverSideRender.default;
	}

	/**
	 * Register a block once. A second copy of this file must not throw.
	 *
	 * @param {string} name     Block name.
	 * @param {Object} settings Editor settings.
	 */
	function register( name, settings ) {
		if ( blocks.getBlockType( name ) ) {
			return;
		}

		blocks.registerBlockType( name, settings );
	}
	var Fragment = element.Fragment;
	var __ = i18n.__;

	var useBlockProps = blockEditor.useBlockProps;
	var RichText = blockEditor.RichText;
	var InspectorControls = blockEditor.InspectorControls;

	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var Button = components.Button;
	var Notice = components.Notice;

	/**
	 * A labelled, reorderable list of plain-text rows.
	 *
	 * Shared by Key Takeaways and Sources, which differ only in their wording.
	 *
	 * @param {Object}   props           Component props.
	 * @param {string[]} props.items     The current rows.
	 * @param {Function} props.onChange  Called with the new array.
	 * @param {string}   props.addLabel  Label for the add button.
	 * @param {string}   props.rowLabel  Accessible label for each row, receives the index.
	 * @param {string}   props.emptyHint Shown when there are no rows yet.
	 * @return {Object} The rendered editor UI.
	 */
	function RowEditor( props ) {
		var items = props.items || [];

		function update( index, value ) {
			var next = items.slice();
			next[ index ] = value;
			props.onChange( next );
		}

		function add() {
			props.onChange( items.concat( [ '' ] ) );
		}

		function remove( index ) {
			props.onChange(
				items.filter( function ( _item, i ) {
					return i !== index;
				} )
			);
		}

		function move( index, delta ) {
			var target = index + delta;

			if ( target < 0 || target >= items.length ) {
				return;
			}

			var next = items.slice();
			var held = next[ index ];
			next[ index ] = next[ target ];
			next[ target ] = held;
			props.onChange( next );
		}

		return el(
			Fragment,
			null,

			items.length === 0
				? el(
						Notice,
						{ status: 'info', isDismissible: false },
						props.emptyHint
				  )
				: null,

			items.map( function ( item, index ) {
				return el(
					'div',
					{ key: index, className: 'digest-row' },

					el( TextareaControl, {
						label: props.rowLabel.replace( '%d', String( index + 1 ) ),
						value: item,
						rows: 2,
						__nextHasNoMarginBottom: true,
						onChange: function ( value ) {
							update( index, value );
						},
					} ),

					el(
						'div',
						{ className: 'digest-row__actions' },

						el(
							Button,
							{
								size: 'small',
								variant: 'tertiary',
								disabled: index === 0,
								onClick: function () {
									move( index, -1 );
								},
							},
							__( 'Up', 'broadside-blocks' )
						),

						el(
							Button,
							{
								size: 'small',
								variant: 'tertiary',
								disabled: index === items.length - 1,
								onClick: function () {
									move( index, 1 );
								},
							},
							__( 'Down', 'broadside-blocks' )
						),

						el(
							Button,
							{
								size: 'small',
								variant: 'tertiary',
								isDestructive: true,
								onClick: function () {
									remove( index );
								},
							},
							__( 'Remove', 'broadside-blocks' )
						)
					)
				);
			} ),

			el(
				Button,
				{ variant: 'secondary', onClick: add },
				props.addLabel
			)
		);
	}

	/* ---------------------------------------------------------------------- *
	 * Short Answer
	 * ---------------------------------------------------------------------- */

	register( 'broadside/short-answer', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var label =
				attributes.label || __( 'The Short Answer', 'broadside-blocks' );

			return el(
				Fragment,
				null,

				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Short Answer', 'broadside-blocks' ) },
						el( TextControl, {
							label: __( 'Label', 'broadside-blocks' ),
							help: __(
								'The small heading above the answer.',
								'broadside-blocks'
							),
							value: attributes.label,
							placeholder: __( 'The Short Answer', 'broadside-blocks' ),
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { label: value } );
							},
						} )
					)
				),

				el(
					'div',
					useBlockProps( {
						className: 'wp-block-digest-short-answer',
					} ),

					el(
						'p',
						{ className: 'wp-block-digest-short-answer__label' },
						label
					),

					el( RichText, {
						tagName: 'p',
						className: 'wp-block-digest-short-answer__body',
						value: attributes.answer,
						allowedFormats: [ 'core/bold', 'core/italic', 'core/link' ],
						placeholder: __(
							'Answer the article’s question in two or three sentences. Write it so it can be quoted on its own.',
							'broadside-blocks'
						),
						onChange: function ( value ) {
							setAttributes( { answer: value } );
						},
					} )
				)
			);
		},

		save: function () {
			return null;
		},
	} );

	/* ---------------------------------------------------------------------- *
	 * Key Takeaways
	 * ---------------------------------------------------------------------- */

	register( 'broadside/takeaways', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var items = attributes.items || [];
			var label = attributes.label || __( 'Key Takeaways', 'broadside-blocks' );

			return el(
				Fragment,
				null,

				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Key Takeaways', 'broadside-blocks' ) },

						el( TextControl, {
							label: __( 'Label', 'broadside-blocks' ),
							value: attributes.label,
							placeholder: __( 'Key Takeaways', 'broadside-blocks' ),
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { label: value } );
							},
						} ),

						el( RowEditor, {
							items: items,
							addLabel: __( 'Add a takeaway', 'broadside-blocks' ),
							/* translators: %d: the takeaway's position in the list, e.g. "Takeaway 2". */
							rowLabel: __( 'Takeaway %d', 'broadside-blocks' ),
							emptyHint: __(
								'Three or four takeaways read best. One sentence each.',
								'broadside-blocks'
							),
							onChange: function ( next ) {
								setAttributes( { items: next } );
							},
						} )
					)
				),

				el(
					'div',
					useBlockProps( { className: 'wp-block-digest-takeaways' } ),

					el(
						'p',
						{ className: 'wp-block-digest-takeaways__label' },
						label
					),

					items.length === 0
						? el(
								'p',
								{ className: 'wp-block-digest-takeaways__item' },
								el(
									'span',
									null,
									__(
										'Add takeaways in the block settings, on the right.',
										'broadside-blocks'
									)
								)
						  )
						: el(
								'ul',
								{ className: 'wp-block-digest-takeaways__list' },
								items.map( function ( item, index ) {
									return el(
										'li',
										{
											key: index,
											className:
												'wp-block-digest-takeaways__item',
										},
										el( 'span', null, item )
									);
								} )
						  )
				)
			);
		},

		save: function () {
			return null;
		},
	} );

	/* ---------------------------------------------------------------------- *
	 * Table of Contents
	 *
	 * There is nothing to edit but the label: the list is derived from the
	 * post's headings at render time. The editor preview says so rather than
	 * pretending to show a list it cannot know yet.
	 * ---------------------------------------------------------------------- */

	register( 'broadside/toc', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var label = attributes.label || __( 'In This Article', 'broadside-blocks' );

			return el(
				Fragment,
				null,

				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Table of Contents', 'broadside-blocks' ) },
						el( TextControl, {
							label: __( 'Label', 'broadside-blocks' ),
							value: attributes.label,
							placeholder: __( 'In This Article', 'broadside-blocks' ),
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { label: value } );
							},
						} )
					)
				),

				el(
					'nav',
					useBlockProps( { className: 'wp-block-digest-toc' } ),

					el(
						'p',
						{ className: 'wp-block-digest-toc__label' },
						label
					),

					el(
						'p',
						{
							className: 'wp-block-digest-toc__list',
							style: { fontStyle: 'italic', opacity: 0.7 },
						},
						__(
							'Built automatically from the headings in this post when it is published.',
							'broadside-blocks'
						)
					)
				)
			);
		},

		save: function () {
			return null;
		},
	} );

	/* ---------------------------------------------------------------------- *
	 * FAQ
	 * ---------------------------------------------------------------------- */

	register( 'broadside/faq', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var items = attributes.items || [];
			var title =
				attributes.title ||
				__( 'Frequently Asked Questions', 'broadside-blocks' );

			function update( index, key, value ) {
				var next = items.map( function ( item, i ) {
					if ( i !== index ) {
						return item;
					}

					var copy = Object.assign( {}, item );
					copy[ key ] = value;

					return copy;
				} );

				setAttributes( { items: next } );
			}

			function add() {
				setAttributes( {
					items: items.concat( [ { question: '', answer: '' } ] ),
				} );
			}

			function remove( index ) {
				setAttributes( {
					items: items.filter( function ( _item, i ) {
						return i !== index;
					} ),
				} );
			}

			return el(
				Fragment,
				null,

				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'FAQ', 'broadside-blocks' ) },

						el( TextControl, {
							label: __( 'Heading', 'broadside-blocks' ),
							value: attributes.title,
							placeholder: __(
								'Frequently Asked Questions',
								'broadside-blocks'
							),
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { title: value } );
							},
						} ),

						el(
							Notice,
							{ status: 'info', isDismissible: false },
							__(
								'These questions also become FAQPage structured data, so search engines can quote them. Write answers that stand on their own.',
								'broadside-blocks'
							)
						),

						items.map( function ( item, index ) {
							return el(
								'div',
								{ key: index, className: 'digest-row' },

								el( TextControl, {
									label: __( 'Question', 'broadside-blocks' ),
									value: item.question || '',
									__nextHasNoMarginBottom: true,
									onChange: function ( value ) {
										update( index, 'question', value );
									},
								} ),

								el( TextareaControl, {
									label: __( 'Answer', 'broadside-blocks' ),
									value: item.answer || '',
									rows: 4,
									__nextHasNoMarginBottom: true,
									onChange: function ( value ) {
										update( index, 'answer', value );
									},
								} ),

								el(
									Button,
									{
										size: 'small',
										variant: 'tertiary',
										isDestructive: true,
										onClick: function () {
											remove( index );
										},
									},
									__( 'Remove question', 'broadside-blocks' )
								)
							);
						} ),

						el(
							Button,
							{ variant: 'secondary', onClick: add },
							__( 'Add a question', 'broadside-blocks' )
						)
					)
				),

				el(
					'section',
					useBlockProps( { className: 'wp-block-digest-faq' } ),

					el(
						'h2',
						{ className: 'wp-block-digest-faq__title' },
						title
					),

					el( 'div', { className: 'wp-block-digest-faq__rule' } ),

					items.length === 0
						? el(
								'p',
								{ style: { fontStyle: 'italic', opacity: 0.7 } },
								__(
									'Add questions in the block settings, on the right.',
									'broadside-blocks'
								)
						  )
						: items.map( function ( item, index ) {
								return el(
									'div',
									{
										key: index,
										className: 'wp-block-digest-faq__item',
									},
									el(
										'h3',
										{ className: 'wp-block-digest-faq__q' },
										item.question ||
											__( 'Untitled question', 'broadside-blocks' )
									),
									el(
										'p',
										{ className: 'wp-block-digest-faq__a' },
										item.answer
									)
								);
						  } )
				)
			);
		},

		save: function () {
			return null;
		},
	} );

	/* ---------------------------------------------------------------------- *
	 * Sources
	 * ---------------------------------------------------------------------- */

	register( 'broadside/sources', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var items = attributes.items || [];
			var label =
				attributes.label || __( 'Sources & References', 'broadside-blocks' );

			return el(
				Fragment,
				null,

				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Sources', 'broadside-blocks' ) },

						el( TextControl, {
							label: __( 'Label', 'broadside-blocks' ),
							value: attributes.label,
							placeholder: __(
								'Sources & References',
								'broadside-blocks'
							),
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { label: value } );
							},
						} ),

						el( RowEditor, {
							items: items,
							addLabel: __( 'Add a source', 'broadside-blocks' ),
							/* translators: %d: the source's position in the list, e.g. "Source 3". */
							rowLabel: __( 'Source %d', 'broadside-blocks' ),
							emptyHint: __(
								'Cite what the reporting rests on. Links are allowed.',
								'broadside-blocks'
							),
							onChange: function ( next ) {
								setAttributes( { items: next } );
							},
						} )
					)
				),

				el(
					'section',
					useBlockProps( { className: 'wp-block-digest-sources' } ),

					el(
						'p',
						{ className: 'wp-block-digest-sources__label' },
						label
					),

					el(
						'ol',
						{ className: 'wp-block-digest-sources__list' },
						items.length === 0
							? el(
									'li',
									{ style: { fontStyle: 'italic', opacity: 0.7 } },
									__(
										'Add sources in the block settings, on the right.',
										'broadside-blocks'
									)
							  )
							: items.map( function ( item, index ) {
									return el(
										'li',
										{ key: index },
										// Deliberately rendered as text in the editor: the
										// front end runs it through wp_kses_post(), and a
										// preview that renders raw HTML would mislead.
										item
									);
							  } )
					)
				)
			);
		},

		save: function () {
			return null;
		},
	} );

	/* ---------------------------------------------------------------------- *
	 * Disclosure Table
	 * ---------------------------------------------------------------------- */

	register( 'broadside/disclosure-table', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var rows = attributes.rows || [];

			var headings = [
				attributes.columnOne || __( 'What to check', 'broadside-blocks' ),
				attributes.columnTwo || __( 'Why it matters', 'broadside-blocks' ),
				attributes.columnThree || __( 'Partner', 'broadside-blocks' ),
			];

			function update( index, key, value ) {
				var next = rows.map( function ( row, i ) {
					if ( i !== index ) {
						return row;
					}

					var copy = Object.assign( {}, row );
					copy[ key ] = value;

					return copy;
				} );

				setAttributes( { rows: next } );
			}

			function add() {
				setAttributes( {
					rows: rows.concat( [
						{ label: '', detail: '', partner: '', url: '' },
					] ),
				} );
			}

			function remove( index ) {
				setAttributes( {
					rows: rows.filter( function ( _row, i ) {
						return i !== index;
					} ),
				} );
			}

			return el(
				Fragment,
				null,

				el(
					InspectorControls,
					null,

					el(
						PanelBody,
						{ title: __( 'Columns', 'broadside-blocks' ) },

						el( TextControl, {
							label: __( 'First column', 'broadside-blocks' ),
							value: attributes.columnOne,
							placeholder: __( 'What to check', 'broadside-blocks' ),
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { columnOne: value } );
							},
						} ),

						el( TextControl, {
							label: __( 'Second column', 'broadside-blocks' ),
							value: attributes.columnTwo,
							placeholder: __( 'Why it matters', 'broadside-blocks' ),
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { columnTwo: value } );
							},
						} ),

						el( TextControl, {
							label: __( 'Third column', 'broadside-blocks' ),
							value: attributes.columnThree,
							placeholder: __( 'Partner', 'broadside-blocks' ),
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { columnThree: value } );
							},
						} )
					),

					el(
						PanelBody,
						{ title: __( 'Rows', 'broadside-blocks' ) },

						el(
							Notice,
							{ status: 'warning', isDismissible: false },
							__(
								'Partner links are always marked rel="sponsored nofollow" and a disclosure line is always printed — if you leave the disclosure empty, Pressroom writes one for you.',
								'broadside-blocks'
							)
						),

						rows.map( function ( row, index ) {
							return el(
								'div',
								{ key: index, className: 'digest-row' },

								el( TextControl, {
									label: __( 'Label', 'broadside-blocks' ),
									value: row.label || '',
									__nextHasNoMarginBottom: true,
									onChange: function ( value ) {
										update( index, 'label', value );
									},
								} ),

								el( TextareaControl, {
									label: __( 'Detail', 'broadside-blocks' ),
									value: row.detail || '',
									rows: 3,
									__nextHasNoMarginBottom: true,
									onChange: function ( value ) {
										update( index, 'detail', value );
									},
								} ),

								el( TextControl, {
									label: __( 'Partner name', 'broadside-blocks' ),
									value: row.partner || '',
									__nextHasNoMarginBottom: true,
									onChange: function ( value ) {
										update( index, 'partner', value );
									},
								} ),

								el( TextControl, {
									label: __( 'Partner URL', 'broadside-blocks' ),
									type: 'url',
									value: row.url || '',
									__nextHasNoMarginBottom: true,
									onChange: function ( value ) {
										update( index, 'url', value );
									},
								} ),

								el(
									Button,
									{
										size: 'small',
										variant: 'tertiary',
										isDestructive: true,
										onClick: function () {
											remove( index );
										},
									},
									__( 'Remove row', 'broadside-blocks' )
								)
							);
						} ),

						el(
							Button,
							{ variant: 'secondary', onClick: add },
							__( 'Add a row', 'broadside-blocks' )
						)
					),

					el(
						PanelBody,
						{ title: __( 'Disclosure', 'broadside-blocks' ) },
						el( TextareaControl, {
							label: __( 'Disclosure line', 'broadside-blocks' ),
							help: __(
								'Leave empty and Pressroom prints a standard affiliate disclosure naming your publication.',
								'broadside-blocks'
							),
							value: attributes.disclosure,
							rows: 4,
							__nextHasNoMarginBottom: true,
							onChange: function ( value ) {
								setAttributes( { disclosure: value } );
							},
						} )
					)
				),

				el(
					'div',
					useBlockProps( {
						className: 'wp-block-digest-disclosure-table',
					} ),

					el(
						'table',
						{
							className:
								'wp-block-digest-disclosure-table__table',
						},

						el(
							'thead',
							null,
							el(
								'tr',
								null,
								headings.map( function ( heading, index ) {
									return el(
										'th',
										{ key: index, scope: 'col' },
										heading
									);
								} )
							)
						),

						el(
							'tbody',
							null,
							rows.length === 0
								? el(
										'tr',
										null,
										el(
											'td',
											{
												colSpan: 3,
												style: {
													fontStyle: 'italic',
													opacity: 0.7,
												},
											},
											__(
												'Add rows in the block settings, on the right.',
												'broadside-blocks'
											)
										)
								  )
								: rows.map( function ( row, index ) {
										return el(
											'tr',
											{ key: index },
											el( 'td', null, row.label ),
											el( 'td', null, row.detail ),
											el(
												'td',
												null,
												row.partner
													? el(
															'span',
															{
																className:
																	'wp-block-digest-disclosure-table__partner',
															},
															row.partner + ' →'
													  )
													: null
											)
										);
								  } )
						)
					),

					el(
						'p',
						{
							className:
								'wp-block-digest-disclosure-table__note',
						},
						attributes.disclosure ||
							__(
								'A standard affiliate disclosure will be printed here.',
								'broadside-blocks'
							)
					)
				)
			);
		},

		save: function () {
			return null;
		},
	} );

	/**
	 * Preview a PHP-rendered block, with an optional inspector.
	 *
	 * These blocks have no saved markup. Without a client registration the
	 * site editor turns them into core/missing and reports that the site
	 * does not support them.
	 *
	 * @param {string}   name       Block name.
	 * @param {string}   title      Shown when the preview is empty (template with no post).
	 * @param {Function} inspector  Optional. Receives the block props, returns elements.
	 */
	function phpBlock( name, title, inspector ) {
		register( name, {
			edit: function ( props ) {
				return el(
					element.Fragment,
					null,
					inspector ? inspector( props ) : null,
					el(
						'div',
						useBlockProps(),
						el( serverSideRender, {
							block: name,
							attributes: props.attributes,
							EmptyResponsePlaceholder: function () {
								return el(
									'p',
									{ className: 'broadside-block-placeholder' },
									title
								);
							},
						} )
					)
				);
			},
			save: function () {
				return null;
			},
		} );
	}

	/**
	 * Category checklist for the section grid.
	 *
	 * An empty selection keeps the theme's view-ranked list. A saved list is
	 * what a language-specific front page uses to differ from the others.
	 *
	 * @param {Object} props Block props.
	 * @return {Object} Inspector controls.
	 */
	function sectionGridInspector( props ) {
		var selected = props.attributes.categories || [];
		var terms = element.useState( [] );
		var list = terms[ 0 ];
		var setList = terms[ 1 ];

		element.useEffect( function () {
			apiFetch( {
				path: '/wp/v2/categories?per_page=100&hide_empty=false&orderby=name&order=asc',
			} )
				.then( function ( rows ) {
					setList( Array.isArray( rows ) ? rows : [] );
				} )
				.catch( function () {
					setList( [] );
				} );
		}, [] );

		function toggle( id ) {
			var next = selected.indexOf( id ) === -1
				? selected.concat( [ id ] )
				: selected.filter( function ( item ) {
						return item !== id;
				  } );

			props.setAttributes( { categories: next } );
		}

		return el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __( 'Sections', 'broadside-blocks' ), initialOpen: true },
				el( TextControl, {
					label: __( 'Heading', 'broadside-blocks' ),
					value: props.attributes.heading || '',
					onChange: function ( value ) {
						props.setAttributes( { heading: value } );
					},
				} ),
				el( TextControl, {
					label: __( 'Columns', 'broadside-blocks' ),
					type: 'number',
					min: 1,
					max: 12,
					value: String( props.attributes.columns || 6 ),
					onChange: function ( value ) {
						props.setAttributes( { columns: parseInt( value, 10 ) || 6 } );
					},
				} ),
				el( TextControl, {
					label: __( 'Headlines per column', 'broadside-blocks' ),
					type: 'number',
					min: 1,
					max: 10,
					value: String( props.attributes.perColumn || 3 ),
					onChange: function ( value ) {
						props.setAttributes( { perColumn: parseInt( value, 10 ) || 3 } );
					},
				} ),
				el(
					'p',
					{ className: 'components-base-control__help' },
					__(
						'Leave every box unticked to rank sections by what readers open. Tick a list to print those categories, in the order you tick them. On a multilingual site, tick the categories of the language this template is for.',
						'broadside-blocks'
					)
				),
				list.map( function ( term ) {
					return el( components.CheckboxControl, {
						key: term.id,
						label: term.name,
						checked: selected.indexOf( term.id ) !== -1,
						onChange: function () {
							toggle( term.id );
						},
					} );
				} )
			)
		);
	}

	phpBlock( 'broadside/utility-bar', __( 'Utility bar', 'broadside-blocks' ) );
	phpBlock( 'broadside/nameplate', __( 'Nameplate', 'broadside-blocks' ) );
	phpBlock( 'broadside/folio', __( 'Folio rule', 'broadside-blocks' ) );
	phpBlock( 'broadside/colophon', __( 'Colophon', 'broadside-blocks' ) );
	phpBlock( 'broadside/newsletter', __( 'Newsletter', 'broadside-blocks' ) );
	phpBlock( 'broadside/lead-promo', __( 'Lead column promo', 'broadside-blocks' ) );
	phpBlock( 'broadside/byline', __( 'Byline', 'broadside-blocks' ) );
	phpBlock( 'broadside/author-bio', __( 'Author bio', 'broadside-blocks' ) );
	phpBlock( 'broadside/standards', __( 'Editorial standards', 'broadside-blocks' ) );
	phpBlock( 'broadside/related', __( 'Continue reading', 'broadside-blocks' ), function ( props ) {
		return el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __( 'Continue reading', 'broadside-blocks' ) },
				el( TextControl, {
					label: __( 'Heading', 'broadside-blocks' ),
					value: props.attributes.heading || '',
					onChange: function ( value ) {
						props.setAttributes( { heading: value } );
					},
				} ),
				el( TextControl, {
					label: __( 'Stories', 'broadside-blocks' ),
					type: 'number',
					min: 1,
					max: 6,
					value: String( props.attributes.count || 3 ),
					onChange: function ( value ) {
						props.setAttributes( { count: parseInt( value, 10 ) || 3 } );
					},
				} )
			)
		);
	} );
	phpBlock( 'broadside/lead-body', __( 'Lead story body', 'broadside-blocks' ), function ( props ) {
		return el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __( 'Lead story', 'broadside-blocks' ) },
				el( TextControl, {
					label: __( 'Paragraphs', 'broadside-blocks' ),
					type: 'number',
					min: 1,
					max: 8,
					value: String( props.attributes.paragraphs || 4 ),
					onChange: function ( value ) {
						props.setAttributes( { paragraphs: parseInt( value, 10 ) || 4 } );
					},
				} ),
				el( TextControl, {
					label: __( 'Continue link', 'broadside-blocks' ),
					value: props.attributes.moreText || '',
					onChange: function ( value ) {
						props.setAttributes( { moreText: value } );
					},
				} )
			)
		);
	} );
	phpBlock( 'broadside/section-grid', __( 'Section grid', 'broadside-blocks' ), sectionGridInspector );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender,
	window.wp.apiFetch
);
