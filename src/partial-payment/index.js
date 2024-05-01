/**
 * External dependencies
 */
import { registerPlugin } from '@wordpress/plugins';
import { ExperimentalOrderMeta, ExperimentalDiscountsMeta } from '@woocommerce/blocks-checkout';
import { getSetting } from '@woocommerce/settings';
import { formatPrice, getCurrencyFromPriceResponse, Currency } from '@woocommerce/price-format';
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	Panel,
	ValidatedTextInput,
	ValidationInputError,
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
	return (
		<ExperimentalDiscountsMeta>
			{settings.active ? (
				<Panel
					className="wc-block-components-partial-payment-panel"
					initialOpen={false}
					hasBorder={false}
					title={
						<span className="wc-block-components-partial-payment-panel__button-text">
							{ /* translators: 1: Wallet amount */ sprintf(__('You have %s in your wallet to spend!', 'woo-wallet'), formatPrice(settings.balance * 100))}
						</span>
					}
				>
					<span>{__("Enter the amount you'd like to redeem", "woo-wallet")}</span>
					<div class="wc-block-components-partial-payment">
						<form
							className="wc-block-components-partial-payment_form"
							id="wc-block-components-partial-payment_form"
						>

							<ValidatedTextInput
								id={textInputId}
								errorId="coupon"
								className="wc-block-components-partial-payment_input"
								label={__(
									'Enter amount',
									'woo-wallet'
								)}
								value={partialPaymentAmount}
								onChange={(newPartialPaymentAmount) => {
									setPartialPaymentAmount(newPartialPaymentAmount);
								}}
								focusOnMount={false}
								validateOnMount={false}
								showError={false}
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