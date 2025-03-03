<footer class="footer">
	<div class="container">
		<p class="m-0 text-center text-white text-uppercase">
			<?php echo $site->footer(); ?>

			
			<?php foreach (Theme::socialNetworks() as $key => $label): ?>
				<ul>
					<li class="nav-item-social">
						<a class="nav-link nav-link-social" href="<?php echo $site->{$key}(); ?>" target="_blank">
							<img class="d-none d-sm-block nav-svg-icon" src="<?php echo DOMAIN_THEME . 'img/' . $key . '.svg' ?>"
							alt="<?php echo $label ?>" />
							<span class="d-inline d-sm-none"><?php echo $label; ?></span>
						</a>
					</li>
				</ul>
			<?php endforeach; ?>

			<span><a href="<?php echo Theme::siteUrl() . 'admin'?>">Control Center</a></span>
		</p>
	</div>
</footer>