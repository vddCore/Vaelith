<div class="about-page">
<div style="margin-top: 6px; margin-right: 2px;" class="float-right">
		<a class="toolbar-primary-button" href="<?php echo HTML_PATH_ADMIN_ROOT . "developers" ?>">
			<i class="fa fa-gears"></i>
			<span class="label"><?php echo $L->g('Developer information'); ?></span>
		</a>
	</div>

	<?php
	echo Bootstrap::pageTitle(array('title' => $L->g('About'), 'icon' => 'info-circle'));

	echo '
	<table class="table table-striped mt-3">
		<tbody>
	';

	echo '<tr>';
	echo '<td>Bludit Version</td>';
	echo '<td>' . BLUDIT_VERSION . '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<td>Bludit Codename</td>';
	echo '<td>' . BLUDIT_CODENAME . '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<td>Bludit Build Number</td>';
	echo '<td>' . BLUDIT_BUILD . '</td>';
	echo '</tr>';

	echo '<tr>';
	echo '<td>Disk usage</td>';
	echo '<td>' . Filesystem::bytesToHumanFileSize(Filesystem::getSize(PATH_ROOT)) . '</td>';
	echo '</tr>';

	echo '
		</tbody>
	</table>
	';
	?>
</div>