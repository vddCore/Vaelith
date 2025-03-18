<?php defined('BLUDIT') or die('Bludit CMS.');

echo Bootstrap::pageTitle(array('title'=>$L->g('Categories'), 'icon'=>'tags'));

echo '
<div class="categories-page">
';

echo '
<div class="toolbar">
	<a class="toolbar-primary-button" href="'.HTML_PATH_ADMIN_ROOT.'new-category"><i class="fa fa-plus"></i> <span class="label">'.$L->g('Add a new category').'</span></a>
</div>
';

echo '
	<table class="table table-striped mt-3">
		<thead>
			<tr>
				<th class="border-bottom-0" scope="col">'.$L->g('Name').'</th>
				<th class="border-bottom-0" scope="col">'.$L->g('URL').'</th>
			</tr>
		</thead>
		<tbody>
	';

	foreach ($categories->keys() as $key) {
		$category = new Category($key);
		echo '<tr>';
		echo '<td><a href="'.HTML_PATH_ADMIN_ROOT.'edit-category/'.$key.'">'.$category->name().'</a></td>';
		echo '<td><a href="'.$category->permalink().'">'.$url->filters('category', false).$key.'</a></td>';
		echo '</tr>';
	}

echo '
		</tbody>
	</table>
</div>
';
