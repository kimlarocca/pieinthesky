<?php
/**
 * The header for our theme.
 *
 * Displays all of the <head> section and everything up till <div id="content">
 *
 * @package storefront
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> <?php storefront_html_tag_schema(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="profile" href="http://gmpg.org/xfn/11">
<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
<link href='http://fonts.googleapis.com/css?family=Gloria+Hallelujah' rel='stylesheet' type='text/css'>
<link href='http://fonts.googleapis.com/css?family=Source+Sans+Pro' rel='stylesheet' type='text/css'>
<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<div id="page" class="hfeed site">
<?php
	do_action( 'storefront_before_header' ); ?>

<!-- slider -->
<div id="slider"><a href="http://www.pieintheskyquilts.com"></a></div>

<!-- sky & cloud animation -->
<div id="stage" class="stage">
  <div id="bg" class="stage"></div>
  <div id="clouds" class="stage"></div>
</div>

<!-- cloud top -->
<div class="cloudtop"></div>
<div class="clear clearClouds"></div>
<script src="http://pieintheskyquilts.com/wp-content/themes/pieinthesky/pie/jquery.easing.1.3.js"></script> 
<script src="http://pieintheskyquilts.com/wp-content/themes/pieinthesky/pie/jquery.spritely-0.4.js"></script> 
<script type="text/javascript">
(function($) {
	$(document).ready(function() {
		//cloud animation
		$('#clouds').pan({fps: 30, speed: 0.5, dir: 'left', depth: 10});	
	});
})(jQuery);	
</script>
<?php
	/**
	 * @hooked storefront_header_widget_region - 10
	 */
	do_action( 'storefront_before_content' ); ?>
<div id="content" class="site-content" tabindex="-1">
<div class="col-full">
<?php
		/**
		 * @hooked woocommerce_breadcrumb - 10
		 */
		do_action( 'storefront_content_top' ); ?>
