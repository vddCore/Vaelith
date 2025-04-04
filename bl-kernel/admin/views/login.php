<?php defined('BLUDIT') or die('Bludit CMS.');

echo '<h1 class="text-center mt-1 font-weight-normal">' . $site->title() . '</h1>';
echo '<h6 class="text-center mb-3">control center</h6>';

echo Bootstrap::formOpen(array());

echo Bootstrap::formInputHidden(array(
	'name' => 'tokenCSRF',
	'value' => $security->getTokenCSRF()
));

echo '
	<span class="textbox-label">' . $L->g('Username') .'</span>
	<div class="form-group">
		<input type="text" dir="auto" value="' . (isset($_POST['username']) ? Sanitize::html($_POST['username']) : '') . '" class="form-control form-control-lg" id="jsusername" name="username" autofocus>
	</div>
	';

echo '
	<span class="textbox-label">' . $L->g('Password') .'</span>
	<div class="form-group">
		<input type="password" class="form-control form-control-lg" id="jspassword" name="password">
	</div>
	';

echo vAdminExtensions::vCheckBox(array(
		'id' => 'remember',
		'name' => 'remember',
		'label' => $L->g('Remember me')
	));
?>

	<div class="form-group mt-3">
		<button type="submit" class="btn btn-primary btn-lg mr-2 w-100" name="save"><?php echo $L->g('Login'); ?></button>
	</div>

<?php echo '</form>'; ?>