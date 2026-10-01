<?php
/**
 * The escape function for the views, and the rule for its use.
 *
 * THE RULE. Each value that a view prints is one of three kinds:
 *
 *   1. Data: a value from the database, from the request or from a vendor
 *      template file. It is text, never markup, in an attribute and between
 *      tags. Print it through epm_esc(). This includes `key`, `value`,
 *      `description`, `tooltip`, `text`, `title`, MAC and IP addresses,
 *      extension numbers, template and file names, and paths.
 *
 *   2. Markup that the view writes itself: the <a class="info"> tooltip
 *      wrapper, the <option> tags, the alert <div>. The view builds it from
 *      literals, so there is nothing to escape.
 *
 *   3. HTML that other code builds. There are two cases, and each has a note
 *      where the view uses it. One is $error_message in
 *      views/tpl/message_box.php. prepare_message_box() joins it with <br />,
 *      and it contains links. The other is the string that generate_gui_html()
 *      returns instead of its group array when a product has no template data.
 *      It is a literal in TemplatesPage.class.php that ends in <br />.
 *
 * The `description`, `text` and `tooltip` values in the vendor JSON files
 * contain no `<`, `>`, `&`, `"` or `'`. They are labels of kind 1, and the
 * escape does not change them.
 *
 * ENT_SUBSTITUTE is necessary. Without it, htmlspecialchars() returns an
 * empty string for invalid UTF-8. One bad byte in a stored config value then
 * empties the field. lib/blob.inc.php has a fallback for invalid UTF-8 in
 * the same data, because such data occurs.
 */

if (!function_exists('epm_esc')) {
    /**
     * Escape a value for HTML text or an HTML attribute.
     *
     * @param  mixed $value Scalar, or null for a key that a row can lack
     * @return string
     */
    function epm_esc($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
    }
}
