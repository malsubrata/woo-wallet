/**
 * External dependencies
 */
import { registerPlugin } from '@wordpress/plugins';
import { ExperimentalOrderMeta, ExperimentalDiscountsMeta } from '@woocommerce/blocks-checkout';
import { getSetting } from '@woocommerce/settings';
import { __, sprintf } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { useState, useRef } from '@wordpress/element';
import {
	ValidatedTextInput,
	ValidationInputError,
	ValidatedTextInputHandle,
	Panel,
	Spinner,
	Button
} from '@woocommerce/blocks-components';

/**
 * Internal dependencies
 */
import './style.scss';

const { extensionCartUpdate } = window.wc.blocksCheckout;
const settings = getSetting('partial-payment_data');

const render = () => {
	const [partialPaymentAmount, setPartialPaymentAmount] = settings.partial_payment_amount ? useState(settings.partial_payment_amount) : useState('');
	const [showSpinner, setShowSpinner] = useState(false);
	const textInputId = `wc-block-components-partial-payment_input`;
	const buttonClickHandler = (e) => {
		e.preventDefault();
		setShowSpinner(true);
		extensionCartUpdate({
			namespace: 'apply-partial-payment',
			data: {
				'amount': partialPaymentAmount,
			},
		}).then( () => {
			setShowSpinner(false);
		});
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

	return (
		<ExperimentalDiscountsMeta>
			{settings.active ? (
				<Panel
					className="wc-block-components-partial-payment-panel"
					initialOpen={false}
					hasBorder={true}
					headingLevel={ 2 }
					title={
						<span className="wc-block-components-partial-payment-panel__button-text">
							{ /* translators: 1: Wallet amount */ sprintf(__('You have %s in your wallet to spend!', 'woo-wallet'), formatedBalance())}
						</span>
					}
				>
					<div class="wc-block-components-partial-payment">
						<form
							className="wc-block-components-partial-payment_form"
							id="wc-block-components-partial-payment_form"
						>

							<ValidatedTextInput
								id={textInputId}
								errorId="partial-payment-error"
								className="wc-block-components-partial-payment_input"
								label={__( 'Enter amount', 'woo-wallet' )}
								value={partialPaymentAmount}
								onChange={(newPartialPaymentAmount) => {
									setPartialPaymentAmount(newPartialPaymentAmount);
								}}
								focusOnMount={ true }
								validateOnMount={ false }
								showError={ false }
							/>
							<Button
								className="wc-block-components-partial-payment_button"
								disabled={!partialPaymentAmount}
								showSpinner={showSpinner}
								type="submit"
								onClick={buttonClickHandler}
							>
								{__(
									'Apply',
									'woo-wallet'
								)}
							</Button>
						</form>
					</div>
				</Panel>
			) : (<></>)}
		</ExperimentalDiscountsMeta>
	);
};

registerPlugin('partial-payment-block', {
	render,
	scope: 'woocommerce-checkout',
});