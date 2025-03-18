<?php defined('BLUDIT') or die('Bludit CMS.'); ?>

<?php echo Bootstrap::formOpen(array('id' => 'jsform', 'class' => 'tab-content')); ?>
<?php echo Bootstrap::formInputHidden(array('name' => 'tokenCSRF', 'value' => $security->getTokenCSRF())); ?>

<?php if (count($plugins['siteSidebar']) == 0): ?>
	<div class="align-middle">
		<?php echo Bootstrap::pageTitle(array('title' => $L->g('Sidebar plugin order'), 'icon' => 'arrows')); ?>

		<h5 style="margin-left: -2px;">
			<span class="label text-muted"><?php $L->p('There are no sidebar-capable plugins enabled right now.'); ?></span>
		</h5>

		<a href="<?php echo HTML_PATH_ADMIN_ROOT . 'plugins' ?>">
			<i class="fa fa-arrow-left"></i>
			<?php $L->p('Back to plugin management') ?>
		</a>
	</div>
<?php else: ?>
	<div class="align-middle">
		<div class="float-right mt-1">
			<button type="button" class="btn btn-primary btn-sm jsbuttonSave" name="save"><?php $L->p('Save') ?></button>
			<a class="btn btn-secondary btn-sm" href="<?php echo HTML_PATH_ADMIN_ROOT . 'plugins' ?>"
				role="button"><?php $L->p('Cancel') ?></a>
		</div>
		<?php echo Bootstrap::pageTitle(array('title' => $L->g('Sidebar plugin order'), 'icon' => 'arrows')); ?>
	</div>
	<div class="alert alert-primary"><?php $L->p('Drag and Drop to sort the plugins') ?></div>

	<?php

	echo Bootstrap::formInputHidden(array(
		'name' => 'plugin-list',
		'value' => ''
	));

	echo '<ul class="list-group list-group-sortable">';
	foreach ($plugins['siteSidebar'] as $plugin) {
		echo '<li class="list-group-item" data-plugin="' . $plugin->className() . '"><span class="fa fa-arrows-v"></span> ' . $plugin->name() . '</li>';
	}
	echo '</ul>';
	?>

	<?php echo Bootstrap::formClose(); ?>

	<script>
		$(document).ready(function () {
			$('.list-group-sortable').sortable({
				placeholderClass: 'list-group-item'
			});

			$(".jsbuttonSave").on("click", function () {
				var tmp = [];
				$("li.list-group-item").each(function () {
					tmp.push($(this).attr("data-plugin"));
				});
				$("#jsplugin-list").attr("value", tmp.join(","));
				$("#jsform").submit();
			});
		});
	</script>
<?php endif; ?>