<?php
$request_uri = $_SERVER['REQUEST_URI'];
$request_tag = substr($request_uri, strrpos($request_uri, '/') + 1);

$all_pages = $pages->getDB(false);
$valid_children = array();

foreach ($all_pages as $key => $fields) {
    if (!isset($fields['tags'])) {
        continue;
    }

    if (!in_array($request_tag, $fields['tags'])) {
        continue;
    }

    array_push($valid_children, new Page($key));
}
?>

<?php if (empty($valid_children)): ?>
    <div class="text-center p-4">
        <?php
        echo $language->p('No pages tagged <b>' . $request_tag . '</b> have been found.');
        return;
        ?>
    </div>
<?php endif ?>

<section class="page">
    <div class="container">
        <div class="row">
            <div class="category-header mx-auto">
                <h3><?php echo $language->p('Displaying posts tagged <b><code>' . $request_tag . '</code></b>'); ?>
                </h3>
            </div>
        </div>
    </div>
</section>

<?php foreach ($valid_children as $child): ?>
    <section class="page">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <!-- Load Bludit Plugins: Page Begin -->
                    <?php Theme::plugins('pageBegin'); ?>
                    <?php $lib->emitPostBrief($child); ?>
                    <?php Theme::plugins('pageEnd'); ?>
                </div>
            </div>
        </div>
    </section>
<?php endforeach ?>