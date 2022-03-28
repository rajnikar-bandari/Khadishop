<?php
/**
 * Template partial used to add content to <head> in Theme Builder.
 * Duplicates partial content from header.php in order to maintain
 * backwards compatibility with child themes.
 */

elegant_description();
elegant_keywords();
elegant_canonical();

/**
* Fires in the head, before {@see wp_head()} is called. This action can be used to
* insert elements into the beginning of the head before any styles or scripts.
*
* @since 1.0
*/
do_action( 'et_head_meta' );
?>
<link rel="pingback" href="<?php bloginfo('pingback_url'); ?>" />

<script type="text/javascript">
  document.documentElement.className = 'js';
</script>
