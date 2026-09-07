/**
 * Pure value logic for the settings app.
 *
 * Kept out of the view components so the rules a field's visibility depends on
 * can be reasoned about — and tested — on their own.
 */

/**
 * Whether a stored checkbox value counts as checked.
 *
 * Checkbox values reach the app in several shapes: the REST save handler writes
 * the literal 'on'/'off', older option rows hold true/1/'1', and action fields
 * use 'yes'/'no'. The toggle and the `show_if` evaluator MUST agree on this — a
 * toggle that renders on while its condition evaluates false hides every
 * dependent field with no way for the user to reach them.
 *
 * @param {*} value Stored value.
 * @return {boolean} True when the value counts as checked.
 */
export function isCheckedValue( value ) {
	return (
		value === 'on' ||
		value === true ||
		value === 1 ||
		value === '1' ||
		value === 'yes'
	);
}

/**
 * Fold each field's default in for keys the stored option never wrote.
 *
 * A field renders from `stored ?? field.default`, so a condition must read the
 * same effective value. Without this a master toggle shows on from its default
 * while every `show_if` depending on it sees `undefined` and hides the
 * dependants — with no way for the user to reach them.
 *
 * Display only: the save payload is built from the raw values in useSettings.
 *
 * @param {Array}  fields       Field definitions for the section.
 * @param {Object} storedValues Values as persisted.
 * @return {Object} Values with defaults folded in.
 */
export function withFieldDefaults( fields, storedValues ) {
	const merged = { ...( storedValues || {} ) };
	( fields || [] ).forEach( ( field ) => {
		if (
			field.name &&
			undefined === merged[ field.name ] &&
			undefined !== field.default
		) {
			merged[ field.name ] = field.default;
		}
	} );
	return merged;
}

/**
 * Compare one stored value against one expected value.
 *
 * 'on'/'off' are matched through the same truthiness rule the checkbox toggle
 * renders with, so a toggle and the fields depending on it cannot disagree.
 * Everything else keeps a loose string comparison, because select and radio
 * values arrive from the REST response as strings.
 *
 * @param {*} value  Stored value.
 * @param {*} equals Expected value.
 * @return {boolean} Whether they match.
 */
function matches( value, equals ) {
	if ( 'on' === equals || 'off' === equals ) {
		return isCheckedValue( value ) === ( 'on' === equals );
	}
	return String( value ) === String( equals );
}

/**
 * Evaluate a single `show_if` condition.
 *
 * @param {Object} cond   Condition with `field` and `equals`.
 * @param {Object} values Section values, defaults already folded in.
 * @return {boolean} Whether the condition holds.
 */
function evaluateCondition( cond, values ) {
	const { field: depField, equals } = cond;
	const value = values[ depField ];
	if ( Array.isArray( equals ) ) {
		return equals.some( ( one ) => matches( value, one ) );
	}
	return matches( value, equals );
}

/**
 * Whether a field should render, given the section's values.
 *
 * @param {Object} field         Field definition.
 * @param {Object} sectionValues Section values, defaults already folded in.
 * @return {boolean} Whether the field is visible.
 */
export function isVisible( field, sectionValues ) {
	if ( ! field.show_if ) {
		return true;
	}
	const conds = Array.isArray( field.show_if )
		? field.show_if
		: [ field.show_if ];
	return conds.every( ( c ) => evaluateCondition( c, sectionValues ) );
}
