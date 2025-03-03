<?php
    global $pages;

    $category_name = $page->category();
    $all_pages = $pages->getDB(false);
    $valid_children = array();

    foreach ($all_pages as $key => $fields) {
        if ($fields['category'] != $category_name) continue;
        if ($fields['template'] == "category_filter") continue;

        array_push($valid_children, new Page($key));
    }
?>

<?php if (empty($valid_children)): ?>
    <div class="text-center p-4">
        <?php
            echo $language->p('No pages in category <b>'.$category_name.'</b> have been found.');
            return;
        ?>
    </div>
<?php endif ?>

<section class="page">
    <div class="container">
        <div class="row">
            <div class="category-header mx-auto">
                <h3><?php echo $language->p('Displaying posts in category<b><code>'.$category_name.'</code></b>'); ?></h3>
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
