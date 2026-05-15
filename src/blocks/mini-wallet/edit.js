/**
 * Wallet Balance Block – Edit Component
 *
 * Renders the block in the Gutenberg editor with a live preview and
 * inspector controls for icon selection, colors, size, and link URL.
 *
 * @package woo-wallet
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ToggleControl,
	TextControl,
	ColorPalette,
	Button,
	Tooltip,
} from '@wordpress/components';
import WALLET_ICONS, { WALLET_ICON_LABELS } from './icons';
import './editor.scss';

/**
 * Default color palette for the color pickers.
 */
const COLORS = [
	{ name: __( 'Black', 'woo-wallet' ), color: '#1e1e1e' },
	{ name: __( 'Dark Gray', 'woo-wallet' ), color: '#555555' },
	{ name: __( 'White', 'woo-wallet' ), color: '#ffffff' },
	{ name: __( 'Purple', 'woo-wallet' ), color: '#483DE0' },
	{ name: __( 'Blue', 'woo-wallet' ), color: '#2563eb' },
	{ name: __( 'Green', 'woo-wallet' ), color: '#16a34a' },
	{ name: __( 'Red', 'woo-wallet' ), color: '#dc2626' },
	{ name: __( 'Orange', 'woo-wallet' ), color: '#ea580c' },
	{ name: __( 'Teal', 'woo-wallet' ), color: '#0d9488' },
	{ name: __( 'Pink', 'woo-wallet' ), color: '#db2777' },
];

/**
 * Edit component.
 *
 * @param {Object} props               Block props.
 * @param {Object} props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {JSX.Element}
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		walletIcon,
		iconSize,
		iconColor,
		balanceColor,
		linkUrl,
		showBalance,
	} = attributes;

	const blockProps = useBlockProps( {
		className: 'wc-block-mini-wallet',
	} );

	// Get the selected icon component.
	const SelectedIcon = WALLET_ICONS[ walletIcon ] || WALLET_ICONS[ 'classic-wallet' ];

	return (
		<>
			<InspectorControls>
				{ /* ── Icon Selection Panel ── */ }
				<PanelBody
					title={ __( 'Wallet Icon', 'woo-wallet' ) }
					initialOpen={ true }
				>
					<div className="woo-wallet-icon-picker">
						<p className="woo-wallet-icon-picker__label">
							{ __( 'Choose an icon', 'woo-wallet' ) }
						</p>
						<div className="woo-wallet-icon-picker__grid">
							{ Object.entries( WALLET_ICONS ).map(
								( [ key, IconComponent ] ) => (
									<Tooltip
										key={ key }
										text={ WALLET_ICON_LABELS[ key ] }
									>
										<Button
											className={ `woo-wallet-icon-picker__button ${
												walletIcon === key
													? 'is-selected'
													: ''
											}` }
											onClick={ () =>
												setAttributes( {
													walletIcon: key,
												} )
											}
											aria-label={ WALLET_ICON_LABELS[ key ] }
											aria-pressed={ walletIcon === key }
										>
											<IconComponent size={ 28 } />
										</Button>
									</Tooltip>
								)
							) }
						</div>
					</div>

					<RangeControl
						label={ __( 'Icon Size', 'woo-wallet' ) }
						value={ iconSize }
						onChange={ ( value ) =>
							setAttributes( { iconSize: value } )
						}
						min={ 16 }
						max={ 48 }
						step={ 2 }
					/>
				</PanelBody>

				{ /* ── Display Settings Panel ── */ }
				<PanelBody
					title={ __( 'Display Settings', 'woo-wallet' ) }
					initialOpen={ true }
				>
					<ToggleControl
						label={ __( 'Show Balance', 'woo-wallet' ) }
						help={
							showBalance
								? __( 'Balance amount is visible.', 'woo-wallet' )
								: __( 'Only the icon will be displayed.', 'woo-wallet' )
						}
						checked={ showBalance }
						onChange={ ( value ) =>
							setAttributes( { showBalance: value } )
						}
					/>
				</PanelBody>

				{ /* ── Color Settings Panel ── */ }
				<PanelBody
					title={ __( 'Color Settings', 'woo-wallet' ) }
					initialOpen={ false }
				>
					<div className="woo-wallet-color-setting">
						<p>{ __( 'Icon Color', 'woo-wallet' ) }</p>
						<ColorPalette
							colors={ COLORS }
							value={ iconColor }
							onChange={ ( value ) =>
								setAttributes( { iconColor: value || '' } )
							}
							clearable={ true }
						/>
					</div>
					{ showBalance && (
						<div className="woo-wallet-color-setting">
							<p>{ __( 'Balance Color', 'woo-wallet' ) }</p>
							<ColorPalette
								colors={ COLORS }
								value={ balanceColor }
								onChange={ ( value ) =>
									setAttributes( { balanceColor: value || '' } )
								}
								clearable={ true }
							/>
						</div>
					) }
				</PanelBody>

				{ /* ── Link Settings Panel ── */ }
				<PanelBody
					title={ __( 'Link Settings', 'woo-wallet' ) }
					initialOpen={ false }
				>
					<TextControl
						label={ __( 'Link URL', 'woo-wallet' ) }
						help={ __(
							'Leave empty to link to the default My Account → Wallet page.',
							'woo-wallet'
						) }
						value={ linkUrl }
						onChange={ ( value ) =>
							setAttributes( { linkUrl: value } )
						}
						placeholder="/my-account/my-wallet/"
					/>
				</PanelBody>
			</InspectorControls>

			{ /* ── Block Preview ── */ }
			<div { ...blockProps }>
				<a
					className="wc-block-mini-wallet__link"
					href="#"
					onClick={ ( e ) => e.preventDefault() }
					aria-label={ __( 'View your wallet', 'woo-wallet' ) }
				>
					<span
						className="wc-block-mini-wallet__icon"
						style={ iconColor ? { color: iconColor } : undefined }
					>
						<SelectedIcon size={ iconSize } />
					</span>
					{ showBalance && (
						<span
							className="wc-block-woo-wallet-balance__amount"
							style={
								balanceColor
									? { color: balanceColor }
									: undefined
							}
						>
							{ /* Placeholder balance shown in editor */ }
							$100.00
						</span>
					) }
				</a>
			</div>
		</>
	);
}
