<div class="plugins-page">
	<div style="margin-top: 6px; margin-right: 2px;" class="float-right">
		<a class="toolbar-primary-button" href="<?php echo HTML_PATH_ADMIN_ROOT . "plugins-position" ?>">
			<i class="fa fa-arrows"></i>
			<span class="label"><?php echo $L->g('Reorder sidebar plugins'); ?></span>
		</a>
	</div>

	<?php echo Bootstrap::pageTitle(array('title' => $L->g('Plugins'), 'icon' => 'puzzle-piece')); ?>

	<input type="text" dir="auto" class="form-control" id="search" placeholder="<?php $L->p('Search') ?>">

	<script>
		$(document).ready(function () {
			$("#search").on("keyup", function () {
				var textToSearch = $(this).val().toLowerCase();
				$(".searchItem").each(function () {
					var item = $(this);
					item.hide();
					item.find(".searchText").each(function () {
						var element = $(this).text().toLowerCase();
						if (element.indexOf(textToSearch) != -1) {
							item.show();
						}
					});
				});
			});
		});
	</script>

	<?php

	echo Bootstrap::formTitle(array('title' => $L->g('Enabled plugins')));

	echo '
<table class="enabled-plugins table">
	<thead>
		<tr>
			<th>' . $L->g("Name") . '</th>
			<th>' . $L->g("Description") . '</th>
			<th>' . $L->g("Version") . '</th>
			<th>' . $L->g("Author") . '</th>
		</tr>
	</thead>
	<tbody>
';

	// Show installed plugins
	foreach ($pluginsInstalled as $plugin) {

		if ($plugin->type() == 'theme') {
			// Do not display theme's plugins
			continue;
		}

		echo '<tr id="' . $plugin->className() . '" class="enabled-plugin searchItem">';

		echo '<td class="align-middle pt-3 pb-3 w-25">
		<div class="searchText">' . $plugin->name() . '</div>
		<div class="mt-1">';
		if (method_exists($plugin, 'form')) {
			echo '<a class="mr-3" href="' . HTML_PATH_ADMIN_ROOT . 'configure-plugin/' . $plugin->className() . '">' . $L->g('Settings') . '</a>';
		}
		echo '<a href="' . HTML_PATH_ADMIN_ROOT . 'uninstall-plugin/' . $plugin->className() . '">' . $L->g('Deactivate') . '</a>';
		echo '</div>';
		echo '</td>';

		echo '<td class="searchText align-middle d-none d-sm-table-cell w-50">';
		echo $plugin->description();
		echo '</td>';

		echo '<td class="text-center align-middle d-none d-lg-table-cell">';
		echo '<span>' . $plugin->version() . '</span>';
		echo '</td>';

		echo '<td class="text-center align-middle d-none d-lg-table-cell">
		<a target="_blank" href="' . $plugin->website() . '">' . $plugin->author() . '</a>
	</td>';

		echo '</tr>';
	}

	echo '
	</tbody>
</table>
';

	echo Bootstrap::formTitle(array('title' => $L->g('Disabled plugins')));

	echo '
<table class="disabled-plugins table">
	<thead>
		<tr>
			<th>' . $L->g("Name") . '</th>
			<th>' . $L->g("Description") . '</th>
			<th>' . $L->g("Version") . '</th>
			<th>' . $L->g("Author") . '</th>
		</tr>
	</thead>
	<tbody>
';


	// Plugins not installed
	$pluginsNotInstalled = array_diff_key($plugins['all'], $pluginsInstalled);
	foreach ($pluginsNotInstalled as $plugin) {

		if ($plugin->type() == 'theme') {
			// Do not display theme's plugins
			continue;
		}
		echo '<tr id="' . $plugin->className() . '" class="disabled-plugin searchItem">';

		echo '<td class="align-middle pt-3 pb-3 w-25">
		<div class="searchText">' . $plugin->name() . '</div>
		<div class="mt-1">
			<a href="' . HTML_PATH_ADMIN_ROOT . 'install-plugin/' . $plugin->className() . '">' . $L->g('Activate') . '</a>
		</div>
	</td>';

		echo '<td class="searchText align-middle d-none d-sm-table-cell w-50">';
		echo $plugin->description();
		echo '</td>';

		echo '<td class="text-center align-middle d-none d-lg-table-cell">';
		echo '<span>' . $plugin->version() . '</span>';
		echo '</td>';

		echo '<td class="text-center align-middle d-none d-lg-table-cell">
		<a target="_blank" href="' . $plugin->website() . '">' . $plugin->author() . '</a>
	</td>';

		echo '</tr>';
	}

	echo '
	</tbody>
</table>
';
	?>

</div>