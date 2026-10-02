<?php
/**
 * Form rows in the layout of the FreePBX modules.
 *
 * A row is an element-container with a label column and a control column.
 * A help text shows when the pointer is on the question mark, as in the
 * other FreePBX modules.
 *
 * The caller escapes $control and $help. $label and $help are translated
 * text, and $control is HTML that the caller builds.
 */

/**
 * Get the HTML of one form row.
 *
 * @param string $id      id of the control, for the label and the help text
 * @param string $label   translated label
 * @param string $control HTML of the control
 * @param string $help    translated help text, or ''
 * @return string
 */
function epm_form_row($id, $label, $control, $help = '') {
	$html = '<div class="element-container"><div class="row"><div class="col-md-12"><div class="row"><div class="form-group">'
		. '<div class="col-md-3"><label class="control-label" for="' . epm_esc($id) . '">' . $label . '</label>';
	if ($help !== '') {
		$html .= ' <i class="fa fa-question-circle fpbx-help-icon" data-for="' . epm_esc($id) . '"></i>';
	}
	$html .= '</div><div class="col-md-9">' . $control . '</div></div></div></div></div>';
	if ($help !== '') {
		$html .= '<div class="row"><div class="col-md-12"><span id="' . epm_esc($id) . '-help" class="help-block fpbx-help-block">' . $help . '</span></div></div>';
	}
	return $html . '</div>';
}

/**
 * Get the HTML of a Yes/No switch, as in the FreePBX modules.
 *
 * @param string $name  field name, also the base of the two ids
 * @param bool   $value TRUE for Yes
 * @param string $attrs more attributes for the two inputs, already escaped
 * @return string
 */
function epm_form_yesno($name, $value, $attrs = '') {
	$n = epm_esc($name);
	return '<span class="radioset">'
		. '<input type="radio" name="' . $n . '" id="' . $n . '_yes" value="Yes"' . ($value ? ' checked' : '') . ' ' . $attrs . '>'
		. '<label for="' . $n . '_yes">' . _('Yes') . '</label>'
		. '<input type="radio" name="' . $n . '" id="' . $n . '_no" value="No"' . ($value ? '' : ' checked') . ' ' . $attrs . '>'
		. '<label for="' . $n . '_no">' . _('No') . '</label>'
		. '</span>';
}
