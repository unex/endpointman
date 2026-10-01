<?php
/**
 * The view layer: plain PHP views, without a template engine.
 *
 * The views are PHP files in views/tpl/. Nothing is compiled or cached.
 *
 * The class keeps an assign()/draw() API, because the callers assign in one
 * place and draw in another. For example, DevicesPage::display_message_box()
 * assigns four variables that a later draw() shows. Variables thus collect on
 * the object, and a view sees each variable that was assigned before the
 * draw.
 */

namespace Endpointman;

class View
{
    /** @var array<string,mixed> Variables assigned so far. */
    private $variables = array();

    /** @var string Directory holding the view files, with a trailing slash. */
    private $view_dir;

    public function __construct($view_dir)
    {
        $this->view_dir = rtrim($view_dir, '/') . '/';
    }

    /**
     * Assign one variable, or each element of an array or object.
     *
     * @param string|array|object $variable
     * @param mixed               $value
     */
    public function assign($variable, $value = null)
    {
        if (is_object($variable)) {
            $variable = get_object_vars($variable);
        }
        if (is_array($variable)) {
            foreach ($variable as $name => $val) {
                $this->variables[$name] = $val;
            }
            return;
        }
        $this->variables[$variable] = $value;
    }

    /**
     * Show a view. Print it, or return it when $return_string is true.
     *
     * @param  string $view_name
     * @param  bool   $return_string
     * @return string|null
     */
    public function draw($view_name, $return_string = false)
    {
        if ($return_string) {
            ob_start();
            $this->render($view_name, $this->variables);
            return ob_get_clean();
        }
        $this->render($view_name, $this->variables);
        return null;
    }

    /**
     * Get each variable assigned so far. A view gives this to $this->render()
     * when it draws a partial, so the partial sees the same variables as the page.
     *
     * @return array<string,mixed>
     */
    public function vars()
    {
        return $this->variables;
    }

    /**
     * Show a view with an explicit variable set, and ignore the assigned
     * variables. Views use this for their partials, so that a partial in a loop
     * can get the current row.
     *
     * @param string               $view_name
     * @param array<string,mixed>  $vars
     */
    public function render($view_name, array $vars = array())
    {
        $__epm_file = $this->view_dir . $view_name . '.php';
        if (!file_exists($__epm_file)) {
            trigger_error("View not found: $view_name");
            echo '<div class="alert alert-danger">' .
                 sprintf(_('View %s not found'), htmlspecialchars($view_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) .
                 '</div>';
            return;
        }
        // The locals start with __epm_, because a view can use any variable name.
        // popup.inc.php assigns a variable called "file". In the included file,
        // $this is this View, so a view draws its partials with $this->render().
        unset($view_name, $vars['__epm_file']);
        extract($vars);
        include $__epm_file;
    }
}
