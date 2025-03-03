<nav class="navbar navbar-expand-md fixed-top text-uppercase">
    <div class="container">
        <ul class="navbar-social">
            <a class="navbar-brand navbar-logo" href="<?php echo Theme::siteUrl(); ?>">            
                <?php echo $site->title(); ?>
            </a>
        </ul>

        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarResponsive">
            <span class="navbar-separator">&gt;&gt;</span> 
            
            <ul class="navbar-nav">
                <!-- Links plugin -->
                <?php
                    if (pluginActivated('vLinksPlugin')) {
                        $plugin = getPlugin('vLinksPlugin');
                        $links = $plugin->getLinks();

                        if (!empty($links)) {
                            foreach ($links as $label => $link) {
                                echo '<li class="nav-item">';
                                echo '  <a class="nav-link" href="'.$link.'">'.$label.'</a>';
                                echo '</li>';
                            }

                            echo '<li class="nav-item">';
                            echo '  <span class="nav-separator">⚬</span>';
                            echo '</li>';
                        }
                    }
                ?>

                <!-- Static pages -->
                <?php foreach ($staticContent as $staticPage): ?>
                <?php if(!$staticPage->parent() && $staticPage->title() != "404") { ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $staticPage->permalink(); ?>"><?php echo $staticPage->title(); ?></a>
                    </li>
                <?php } endforeach ?>

                <!-- RSS -->
                <?php if (Theme::rssUrl()): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo Theme::rssUrl() ?>" target="_blank">
                        <img class="d-none d-sm-block nav-svg-icon text-primary" src="<?php echo DOMAIN_THEME.'img/rss.svg' ?>" alt="RSS" />
                        <span class="d-inline d-sm-none">RSS</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
