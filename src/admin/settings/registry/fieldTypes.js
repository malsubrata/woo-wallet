import { applyFilters } from '@wordpress/hooks';

import TextField from '../fields/TextField';
import NumberField from '../fields/NumberField';
import PasswordField from '../fields/PasswordField';
import CheckboxField from '../fields/CheckboxField';
import SelectField from '../fields/SelectField';
import MultiSelectField from '../fields/MultiSelectField';
import MulticheckField from '../fields/MulticheckField';
import RadioField from '../fields/RadioField';
import TextareaField from '../fields/TextareaField';
import HtmlField from '../fields/HtmlField';
import AttachmentField from '../fields/AttachmentField';
import ColorField from '../fields/ColorField';
import SectionHeading from '../fields/SectionHeading';

const BUILT_IN_TYPES = {
	text:        TextField,
	url:         TextField,
	rand:        TextField,
	secret:      PasswordField,
	email:       TextField,
	number:      NumberField,
	password:    PasswordField,
	checkbox:    CheckboxField,
	select:      SelectField,
	multiselect: MultiSelectField,
	multicheck:  MulticheckField,
	radio:       RadioField,
	textarea:    TextareaField,
	html:        HtmlField,
	wysiwyg:     TextareaField,
	attachment:  AttachmentField,
	file:        AttachmentField,
	color:       ColorField,
	section_heading: SectionHeading,
};

export function getFieldTypes() {
	return applyFilters( 'woo_wallet.settings.fieldTypes', { ...BUILT_IN_TYPES } );
}
