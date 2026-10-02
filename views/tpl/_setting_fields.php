<?php
/**
 * The fields of one settings section, for the phone settings popup.
 *
 * generate_gui_html() builds the fields. Each field has a 'type' that selects
 * the control, and a 'description'. The other keys depend on the type. A
 * 'tooltip' from the package shows as the help text of the field.
 *
 * Each value prints through epm_esc(). The markup is the markup of this file.
 *
 * @var array $fields The 'data' list of one section
 */
if (!isset($fields) || !is_array($fields)) {
    $fields = array();
}

$help = function (array $field) {
    return isset($field['tooltip']) ? '<small class="form-text text-muted">' . epm_esc($field['tooltip']) . '</small>' : '';
};
$row = function ($id, $label, $control) {
    echo '<div class="form-group row"><label class="col-sm-4 col-form-label" for="' . epm_esc($id) . '">' . $label . '</label>'
        . '<div class="col-sm-8">' . $control . '</div></div>' . "\n";
};

foreach ($fields as $field) {
    $key = isset($field['key']) ? $field['key'] : '';
    $label = isset($field['description']) ? epm_esc($field['description']) : '';
    switch ($field['type']) {
        case 'input':
            $row($key, $label, '<input type="text" class="form-control form-control-sm" name="' . epm_esc($key) . '" id="' . epm_esc($key) . '" value="' . epm_esc($field['value']) . '"'
                . (isset($field['max_chars']) ? ' maxlength="' . epm_esc($field['max_chars']) . '"' : '') . '>' . $help($field));
            break;

        case 'textarea':
            $row($key, $label, '<textarea class="form-control form-control-sm" rows="' . epm_esc(isset($field['rows']) ? $field['rows'] : 2) . '" name="' . epm_esc($key) . '" id="' . epm_esc($key) . '">'
                . epm_esc($field['value']) . '</textarea>' . $help($field));
            break;

        case 'radio':
            $control = '';
            $first = $key;
            foreach ($field['data'] as $n => $option) {
                $id = $option['key'] . '_' . $n;
                if ($n === 0) {
                    $first = $id;
                }
                $control .= '<div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="' . epm_esc($option['key']) . '" id="' . epm_esc($id) . '" value="' . epm_esc($option['value']) . '"'
                    . (!empty($option['checked']) ? ' checked' : '') . '><label class="form-check-label" for="' . epm_esc($id) . '">' . epm_esc($option['description']) . '</label></div>';
            }
            $row($first, $label, $control . $help($field));
            break;

        case 'list':
            $control = '<select class="form-control form-control-sm" name="' . epm_esc($key) . '" id="' . epm_esc($key) . '">';
            foreach ($field['data'] as $option) {
                $control .= '<option value="' . epm_esc($option['value']) . '"' . (!empty($option['selected']) ? ' selected' : '') . '>' . epm_esc($option['description']) . '</option>';
            }
            $row($key, $label, $control . '</select>' . $help($field));
            break;

        case 'checkbox':
            $row($key, $label, '<input type="checkbox" class="mt-2" name="' . epm_esc($key) . '" id="' . epm_esc($key) . '" value="' . epm_esc($field['value']) . '"'
                . (!empty($field['checked']) ? ' checked' : '') . '>' . $help($field));
            break;

        case 'group':
            echo '<h3 class="h5 mt-3">' . $label . "</h3>\n";
            break;

        case 'header':
            echo '<h4 class="h6 mt-2">' . $label . "</h4>\n";
            break;
    }
}
