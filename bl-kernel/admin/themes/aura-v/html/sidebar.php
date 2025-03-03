<!-- Use .flex-column to set a vertical direction -->
<ul class="nav flex-column pt-4">

	<li class="nav-item" style="margin-left: -4px;">
		<span class="nav-title">CONTROL PANEL</span>
	</li>

	<li class="nav-item">
		<a class="nav-link" href="<?php echo HTML_PATH_ADMIN_ROOT.'dashboard' ?>"><span class="fa fa-dashboard"></span><span class="nav-label"><?php $L->p('Dashboard') ?></span></a>
	</li>
	<li class="nav-item">
		<a class="nav-link" target="_blank" href="<?php echo HTML_PATH_ROOT ?>"><span class="fa fa-home"></span><span class="nav-label"><?php $L->p('Website') ?></span></a>
	</li>

	<li class="nav-item mt-3">
		<h4><?php $L->p('Create') ?></h4>
	</li>
	<li class="nav-item">
		<a class="nav-link" href="<?php echo HTML_PATH_ADMIN_ROOT.'new-content' ?>"><span class="fa fa-plus-circle"></span><span class="nav-label"><?php $L->p('New content') ?></span></a>
	</li>

	<?php if (!checkRole(array('admin'),false)): ?>
	<li class="nav-item">
		<a class="nav-link" href="<?php echo HTML_PATH_ADMIN_ROOT.'content' ?>"><span class="fa fa-archive"></span><span class="nav-label"><?php $L->p('Content') ?></span></a>
	</li>
	<li class="nav-item">
		<a class="nav-link" href="<?php echo HTML_PATH_ADMIN_ROOT.'edit-user/'.$login->username() ?>"><span class="fa fa-user"></span><span class="nav-label"><?php $L->p('Profile') ?></span></a>
	</li>
	<?php endif; ?>

	<?php if (checkRole(array('admin'),false)): ?>

	<li class="nav-item mt-3">
		<h4><?php $L->p('Manage') ?></h4>
	</li>
	<li class="nav-item">
		<a class="nav-link" href="<?php echo HTML_PATH_ADMIN_ROOT.'content' ?>"><span class="fa fa-folder"></span><span class="nav-label"><?php $L->p('Content') ?></span></a>
	</li>

	<li class="nav-item">
		<a class="nav-link" href="<?php echo HTML_PATH_ADMIN_ROOT.'categories' ?>"><span class="fa fa-bookmark"></span><span class="nav-label"><?php $L->p('Categories') ?></span></a>
	</li>
	<li class="nav-item">
		<a class="nav-link" href="<?php echo HTML_PATH_ADMIN_ROOT.'users' ?>"><span class="fa fa-users"></span><span class="nav-label"><?php $L->p('Users') ?></span></a>
	</li>

	<li class="nav-item mt-3">
		<h4><?php $L->p('Settings') ?></h4>
	</li>
	<li class="nav-item">
		<a class="nav-link" href="<?php echo HTML_PATH_ADMIN_ROOT.'settings' ?>"><span class="fa fa-gear"></span><span class="nav-label"><?php $L->p('General') ?></span></a>
	</li>
	<li class="nav-item">
		<a class="nav-link" href="<?php echo HTML_PATH_ADMIN_ROOT.'plugins' ?>"><span class="fa fa-puzzle-piece"></span><span class="nav-label"><?php $L->p('Plugins') ?></span></a>
	</li>
	<li class="nav-item">
		<a class="nav-link" href="<?php echo HTML_PATH_ADMIN_ROOT.'themes' ?>"><span class="fa fa-desktop"></span><span class="nav-label"><?php $L->p('Themes') ?></span></a>
	</li>
	<li class="nav-item">
		<a class="nav-link" href="<?php echo HTML_PATH_ADMIN_ROOT.'about' ?>"><span class="fa fa-info"></span><span class="nav-label"><?php $L->p('About') ?></span></a>
	</li>

	<?php endif; ?>

	<?php if (checkRole(array('admin', 'editor'),false)): ?>

		<?php
			if (!empty($plugins['adminSidebar'])) {
				echo '<li class="nav-item"><hr></li>';
				foreach ($plugins['adminSidebar'] as $pluginSidebar) {
					echo '<li class="nav-item">';
					echo $pluginSidebar->adminSidebar();
					echo '</li>';
				}
			}
		?>

	<?php endif; ?>

	<li class="nav-item mt-5">
		<a class="nav-link" href="<?php echo HTML_PATH_ADMIN_ROOT.'logout' ?>"><span class="fa fa-arrow-circle-right"></span><span class="nav-label"><?php $L->p('Logout') ?></span></a>
	</li>
</ul>
