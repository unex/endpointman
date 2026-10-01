<?php
    $nav = array();
    $content = array();

    foreach($tabs as $view => $name) {
        $first = $view === array_key_first($tabs);
        $vname = str_replace(".", "_", pathinfo($view)['filename']);

        array_push($nav, '<li role="presentation" class="' . ($first ? "active" : "") . '"><a href="#' . $vname . '" aria-controls="' . $vname . '" role="tab" data-toggle="tab" aria-expanded="false">' . $name . '</a></li>');
        array_push($content, '<div role="tabpanel" class="tab-pane display' . ($first ? " active" : "") . '" id="' . $vname . '">' . load_view($view) . '</div>');
    }
?>

<div role="tabpanel">
    <ul class="nav nav-tabs" role="tablist">
        <?php echo join(PHP_EOL, $nav); ?>
    </ul>

    <div class="tab-content">
        <?php echo join(PHP_EOL, $content); ?>
    </div>
</div>
