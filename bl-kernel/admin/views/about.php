<?php

echo Bootstrap::pageTitle(array('title'=>$L->g('About'), 'icon'=>'info-circle'));

echo '
<table class="table table-striped mt-3">
	<tbody>
';

echo '<tr>';
echo '<td>Bludit Version</td>';
echo '<td>'.BLUDIT_VERSION.'</td>';
echo '</tr>';

echo '<tr>';
echo '<td>Bludit Codename</td>';
echo '<td>'.BLUDIT_CODENAME.'</td>';
echo '</tr>';

echo '<tr>';
echo '<td>Bludit Build Number</td>';
echo '<td>'.BLUDIT_BUILD.'</td>';
echo '</tr>';

echo '<tr>';
echo '<td>Disk usage</td>';
echo '<td>'.Filesystem::bytesToHumanFileSize(Filesystem::getSize(PATH_ROOT)).'</td>';
echo '</tr>';

echo '<tr>';
echo '<td><a href="'.HTML_PATH_ADMIN_ROOT.'developers'.'">Bludit Developers</a></td>';
echo '<td></td>';
echo '</tr>';

echo '
	</tbody>
</table>
';
