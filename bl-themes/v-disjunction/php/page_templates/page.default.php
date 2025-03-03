<section class="page">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Load Bludit Plugins: Page Begin -->
                <?php Theme::plugins('pageBegin'); ?>

                <div class="post-title">
                    <h3 class="title">
                        <?php echo $page->title(); ?>
                    </h3>
                    <?php
                        if ($lib->canEditPosts()) {
                            echo '<a class="post-title-icon" href="admin/edit-content/' . $page->slug() . '"><img src="' . DOMAIN_THEME . 'img/edit.svg" alt="Edit"></img><span class="icon-label">EDIT</span></a>';
                        }
                    ?>
                </div>

                <?php if ($page->description()): ?>
                    <p class="page-description">
                        <?php echo $page->description(); ?>
                    </p>
                <?php endif ?>

                <?php if ($page->coverImage()): ?>
                <div class="page-cover-image py-6 mb-4" style="background-image: url('<?php echo $page->coverImage(); ?>');">
                    <div style="height: 300px;"></div>
                </div>
                <?php endif ?>

                <div class="post-content">
                    <?php echo $page->content(); ?>
                </div>

                <div class="post-sub">
                    <p class="meta">
                        <?php if(!($page->isStatic())) {
                            $lib->emitMetaData($page);
                        }?>
                    </p>
                </div>

                <?php Theme::plugins('pageEnd'); ?>
            </div>
        </div>
    </div>
</section>
