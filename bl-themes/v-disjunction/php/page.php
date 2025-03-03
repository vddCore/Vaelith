<?php
    $tpl = $page->template();
    $tpl_page_path = THEME_DIR_PHP.'page_templates/page.'.$tpl.'.php';

    if (empty($tpl) || !file_exists($tpl_page_path)) {
        include(THEME_DIR_PHP.'page_templates/page.default.php');
    } else {
        include($tpl_page_path);
    }
?>
