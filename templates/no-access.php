<?php
/**
 * The Template for displaying locked wallet content.
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/no-access.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author  Subrata Mal
 * @version     1.1.8
 * @package WooWallet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<p>
	<?php esc_html_e( 'Your wallet account is locked please contact site owner for more details.', 'woo-wallet' ); ?>
</p>
