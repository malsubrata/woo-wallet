
import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';
import { getSetting } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import './style.scss';

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

const formatedBalance = () => {
	const { balance, currency_symbol, decimal_separator, thousand_separator, decimals } = settings;
	// Ensure that 'amount' is a valid number
    let numericAmount = parseFloat(balance);
    if (isNaN(numericAmount)) {
        return amount; // Return the original value if it's not a valid number
    }
    // Format the amount to the required number of decimal places
    let fixedAmount = numericAmount.toFixed(decimals);
    // Split the integer and decimal parts
    let parts = fixedAmount.split('.');
    // Add thousand separator to the integer part only
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousand_separator);
    // Rejoin integer and decimal parts with the correct decimal separator
    let formattedAmount = parts.join(decimal_separator);
    return decodeEntities(`${currency_symbol}${formattedAmount}`);
}

const CurrentBalance = () => {
	return (
		<span>
			&nbsp;{ /* translators: 1: Wallet amount */ sprintf(__('| Current Balance: %s', 'woo-wallet'), formatedBalance())}
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
