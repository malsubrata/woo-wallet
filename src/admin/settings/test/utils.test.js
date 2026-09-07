/**
 * Regression cover for `show_if` visibility on upgraded and never-saved sites.
 *
 * The evaluator used a strict string compare against the raw stored value while
 * the checkbox toggle rendered from `stored ?? field.default` and accepted
 * several truthy shapes. A master toggle therefore showed on while every field
 * depending on it stayed hidden — leaving `partial_payment_tax_treatment`, the
 * setting that fixes double-VAT on taxed top-ups, unreachable with no
 * indication it existed.
 */

import { isCheckedValue, isVisible, withFieldDefaults } from '../utils';

const master = {
	name: 'is_enable_partial_payment',
	type: 'checkbox',
	default: 'on',
};
const dependant = {
	name: 'partial_payment_tax_treatment',
	type: 'select',
	show_if: { field: 'is_enable_partial_payment', equals: 'on' },
};

describe( 'isCheckedValue', () => {
	it.each( [ 'on', true, 1, '1', 'yes' ] )( 'treats %p as checked', ( v ) => {
		expect( isCheckedValue( v ) ).toBe( true );
	} );

	it.each( [ 'off', false, 0, '0', 'no', '', undefined, null ] )(
		'treats %p as unchecked',
		( v ) => {
			expect( isCheckedValue( v ) ).toBe( false );
		}
	);
} );

describe( 'withFieldDefaults', () => {
	it( 'fills a key the stored option never wrote', () => {
		expect( withFieldDefaults( [ master ], {} ) ).toEqual( {
			is_enable_partial_payment: 'on',
		} );
	} );

	it( 'never overrides a stored value, including a falsy one', () => {
		expect(
			withFieldDefaults( [ master ], {
				is_enable_partial_payment: 'off',
			} )
		).toEqual( { is_enable_partial_payment: 'off' } );
	} );

	it( 'leaves fields with no default absent', () => {
		expect(
			withFieldDefaults( [ { name: 'is_enable_gateway_charge' } ], {} )
		).toEqual( {} );
	} );

	it( 'tolerates missing values', () => {
		expect( withFieldDefaults( [ master ], undefined ) ).toEqual( {
			is_enable_partial_payment: 'on',
		} );
	} );
} );

describe( 'isVisible', () => {
	it( 'shows a dependant when the master is stored as the literal on', () => {
		expect(
			isVisible( dependant, { is_enable_partial_payment: 'on' } )
		).toBe( true );
	} );

	// The upgraded-site case: a pre-1.6.x option row holding a boolean or int.
	it.each( [ true, 1, '1', 'yes' ] )(
		'shows a dependant when the master is stored as %p',
		( stored ) => {
			expect(
				isVisible( dependant, {
					is_enable_partial_payment: stored,
				} )
			).toBe( true );
		}
	);

	// The never-saved case: the key is absent and the toggle renders from default.
	it( 'shows a dependant when the master key is missing but defaults on', () => {
		const values = withFieldDefaults( [ master, dependant ], {} );
		expect( isVisible( dependant, values ) ).toBe( true );
	} );

	it( 'hides a dependant when the master is off', () => {
		expect(
			isVisible( dependant, { is_enable_partial_payment: 'off' } )
		).toBe( false );
	} );

	it.each( [ false, 0, '0', 'no' ] )(
		'hides a dependant when the master is stored as %p',
		( stored ) => {
			expect(
				isVisible( dependant, {
					is_enable_partial_payment: stored,
				} )
			).toBe( false );
		}
	);

	it( 'requires every condition of a multi-condition show_if', () => {
		const field = {
			name: 'gateway_charge_amount',
			show_if: [
				{ field: 'is_enable_wallet_topup', equals: 'on' },
				{ field: 'is_enable_gateway_charge', equals: 'on' },
			],
		};
		expect(
			isVisible( field, {
				is_enable_wallet_topup: true,
				is_enable_gateway_charge: 'off',
			} )
		).toBe( false );
		expect(
			isVisible( field, {
				is_enable_wallet_topup: true,
				is_enable_gateway_charge: 1,
			} )
		).toBe( true );
	} );

	it( 'still compares non-boolean values as strings', () => {
		const field = {
			name: 'x',
			show_if: {
				field: 'partial_payment_tax_treatment',
				equals: 'tax_inclusive_wallet',
			},
		};
		expect(
			isVisible( field, {
				partial_payment_tax_treatment: 'tax_inclusive_wallet',
			} )
		).toBe( true );
		expect(
			isVisible( field, { partial_payment_tax_treatment: 'payment' } )
		).toBe( false );
	} );

	it( 'shows a field with no show_if', () => {
		expect( isVisible( { name: 'product_title' }, {} ) ).toBe( true );
	} );
} );
