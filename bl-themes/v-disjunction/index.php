<!DOCTYPE html>
<html lang="<?php echo Theme::lang() ?>">
<head>
	<meta charset="UTF-8">

	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<?php echo Theme::favicon('img/favicon.png'); ?>

	<?php echo Theme::metaTagTitle(); ?>
	<?php echo Theme::metaTagDescription(); ?>
	
	<?php echo Theme::cssBootstrap(); ?>
	<?php echo Theme::css('css/style.css'); ?>
	<?php echo Theme::cssLineAwesome(); ?>
	
	<?php Theme::plugins('siteHead'); ?>
</head>

<body>
	<?php Theme::plugins('siteBodyBegin'); ?>

	<?php include(THEME_DIR_PHP.'navbar.php'); ?>

	<?php
		switch ($WHERE_AM_I) {
			case 'home': include(THEME_DIR_PHP.'home.php'); break;
			case 'page': include(THEME_DIR_PHP.'page.php'); break;
			case 'category': include(THEME_DIR_PHP.'category.php'); break;
			default: {
				echo "<center>Unhandled page <b>$WHERE_AM_I</b>.</center>";
				break;
			} 
		}
	?>

	<?php include(THEME_DIR_PHP.'footer.php'); ?>
	<?php echo Theme::jquery(); ?>
	<?php echo Theme::jsBootstrap(); ?>

	<?php Theme::plugins('siteBodyEnd'); ?>
</body>
</html>
