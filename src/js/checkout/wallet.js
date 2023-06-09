
import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';
import { formatPrice, getCurrencyFromPriceResponse, Currency } from '@woocommerce/price-format';

const settings = getSetting( 'wallet_data', {} );

const defaultLabel = __(
	'Wallet Payment',
	'woo-wallet'
);

const label = decodeEntities( settings.title ) || defaultLabel;
/**
 * Content component
 */
const Content = () => {
	return decodeEntities( settings.description || '' );
};

const CurrentBalance = () => {
	return (
		<span>
			&nbsp;{ /* translators: 1: Wallet amount */ sprintf(__('| Current Balance: %s', 'woo-wallet'), formatPrice( settings.balance * 100 ))}
		</span>
	);
}
/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = ( props ) => {
	const { PaymentMethodLabel } = props.components;
	return <><PaymentMethodLabel text={ label } /> <CurrentBalance /></>;
};

/**
 * Wallet payment method config object.
 */
const Wallet = {
	name: "wallet",
	label: <Label />,
	content: <Content />,
	edit: <Content />,
	canMakePayment: () => settings.canMakePayment,
	ariaLabel: label,
	supports: {
		features: settings.supports,
	},
};

registerPaymentMethod( Wallet );
