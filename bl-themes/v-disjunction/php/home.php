<?php if (empty($content)): ?>
    <div class="text-center p-4">
        <?php $language->p('No pages found') ?>
    </div>
<?php endif ?>

<?php foreach ($content as $page): ?>
<section class="home-page">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <?php Theme::plugins('pageBegin'); ?>
                <?php $lib->emitPostBrief($page); ?>
                <?php Theme::plugins('pageEnd'); ?>
            </div>
        </div>
    </div>
</section>
<?php endforeach ?>

<?php if (Paginator::numberOfPages() > 1): ?>
<nav class="paginator">
    <ul class="pagination flex-wrap justify-content-center">

    <?php if (Paginator::showPrev()): ?>
        <li class="page-item mr-2">
            <a class="page-link" href="<?php echo Paginator::previousPageUrl() ?>" tabindex="-1">&lt;&lt; <?php echo $L->get('Previous'); ?></a>
        </li>
    <?php endif; ?>

    <li class="page-item <?php if (Paginator::currentPage() == 1) echo 'disabled' ?>">
        <a class="page-link" href="<?php echo Theme::siteUrl() ?>"><?php echo $L->get('Home'); ?></a>
    </li>

    <?php if (Paginator::showNext()): ?>
        <li class="page-item ml-2">
            <a class="page-link" href="<?php echo Paginator::nextPageUrl() ?>"><?php echo $L->get('Next'); ?>&gt;&gt;</a>
        </li>
    <?php endif; ?>

    </ul>
</nav>
<?php endif ?>
