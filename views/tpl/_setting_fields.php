<?php
/**
 * The rows of one settings section. The specifics popup and the template
 * editor share this file, so the two pages show the same widgets.
 *
 * generate_gui_html() builds the rows. Each row has a 'type' that selects the
 * widget, and a 'description'. The other keys depend on the type.
 *
 * Each field below is kind 1 under the rule in lib/esc.inc.php (data, not
 * markup), so each one prints through epm_esc(). `key` and `value` are
 * attribute data. `description` is the label text. `max_chars`, `rows` and
 * `cols` come from the same vendor file and go into attributes. The only
 * markup is the <a class="info"> wrapper and the tags that this file writes.
 *
 * `tooltip` arrives raw, as the other fields do, and is escaped once, here.
 *
 * 'checked' and 'selected' have no escape. generate_form_data() sets each one
 * to the literal of its name, or leaves the key out. They are never data.
 *
 * @var array $fields The 'data' list of one section
 */
if (!isset($fields) || !is_array($fields)) {
    $fields = array();
}

/** Label cell contents: the description, in the tooltip link if it has one. */
$label = function (array $field) {
    if (isset($field['tooltip'])) {
        return '<a href="#" class="info">' . epm_esc($field['description']) .
               '<span>' . epm_esc($field['tooltip']) . '</span></a>';
    }
    return epm_esc($field['description']);
};

foreach ($fields as $field) {
    echo "<tr>\n";
    switch ($field['type']) {
        case 'input':
            $size = isset($field['max_chars']) ? $field['max_chars'] : 90;
            echo "<td nowrap>" . $label($field) . ":</td><td nowrap>" .
                 "<input type='text' name='" . epm_esc($field['key']) . "' id='" . epm_esc($field['key']) .
                 "' value='" . epm_esc($field['value']) . "' size=\"" . epm_esc($size) . "\">";
            break;

        case 'textarea':
            $rows = isset($field['rows']) ? $field['rows'] : 2;
            $cols = isset($field['cols']) ? $field['cols'] : 20;
            echo "<td nowrap>" . $label($field) . ":</td><td nowrap>" .
                 "<textarea rows=\"" . epm_esc($rows) . "\" cols=\"" . epm_esc($cols) .
                 "\" name='" . epm_esc($field['key']) . "' id='" . epm_esc($field['key']) . "'>" .
                 epm_esc($field['value']) . "</textarea>";
            break;

        case 'radio':
            echo "<td nowrap>" . $label($field) . ":</td><td nowrap>";
            foreach ($field['data'] as $option) {
                $checked = array_key_exists('checked', $option) ? $option['checked'] : '';
                echo "[<label>" . $label($option) . ": " .
                     "<input type='radio' name='" . epm_esc($option['key']) . "' id='" . epm_esc($option['key']) .
                     "' value='" . epm_esc($option['value']) . "' {$checked}></label>]";
            }
            break;

        case 'list':
            echo "<td nowrap>" . $label($field) . ":</td><td nowrap>" .
                 "<select name='" . epm_esc($field['key']) . "' id='" . epm_esc($field['key']) . "'>";
            foreach ($field['data'] as $option) {
                $selected = array_key_exists('selected', $option) ? $option['selected'] : '';
                echo "<option value='" . epm_esc($option['value']) . "' {$selected}>" .
                     epm_esc($option['description']) . "</option>";
            }
            echo "</select>";
            break;

        case 'checkbox':
            echo "<td nowrap>" . $label($field) . ":</td><td nowrap>" .
                 "<input type='checkbox' name='" . epm_esc($field['key']) . "' id='" . epm_esc($field['key']) .
                 "' value='" . epm_esc($field['value']) . "'>";
            break;

        case 'break':
            echo "<td nowrap colspan=\"2\">&nbsp;";
            break;

        case 'group':
            echo "<td nowrap colspan=\"2\"><hr><H3>" . $label($field) . "</H3>";
            break;

        case 'header':
            echo "<td nowrap colspan=\"2\"><strong>" . $label($field) . "</strong>";
            break;

        default:
            // An unknown type opens a cell here, so that the closing </td> below keeps
            // the row valid.
            echo "<td nowrap colspan=\"2\">";
            break;
    }
    echo "</td>\n</tr>\n";
}
