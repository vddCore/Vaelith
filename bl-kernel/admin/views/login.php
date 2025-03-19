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

	<!-- <div class="form-check">
		<label class="v-checkbox">
			<input type="checkbox" id="jsremember" name="remember">
			<svg viewBox="0 0 64 64">
				<path d="M 0 16 V 56 A 8 8 90 0 0 8 64 H 56 A 8 8 90 0 0 64 56 V 8 A 8 8 90 0 0 56 0 H 8 A 8 8 90 0 0 0 8 V 16 L 32 48 L 64 16 V 8 A 8 8 90 0 0 56 0 H 8 A 8 8 90 0 0 0 8 V 56 A 8 8 90 0 0 8 64 H 56 A 8 8 90 0 0 64 56 V 16" pathLength="575.0541381835938" class="path"></path>
			</svg>

			<span class="label-text"><?php echo $L->g('Remember me'); ?></span>
		</label>
	</div> -->

	<div class="form-group mt-3">
		<button type="submit" class="btn btn-primary btn-lg mr-2 w-100" name="save"><?php echo $L->g('Login'); ?></button>
	</div>

<?php echo '</form>'; ?>