<?php

defined( 'ABSPATH' ) || exit;

/**
 * Available variables
 *
 * @var string $tab
 * @var string $urlGeneral
 * @var string $urlOrderStatus
 * @var string $urlRestApi
 * @var string $urlTools
 */

?>

<div class="wrap lmfwc">

	<?php settings_errors(); ?>

    <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
        <a href="<?php echo esc_url( $urlGeneral ); ?>" class="nav-tab <?= $tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <span><?php esc_html_e( 'General', 'license-manager-for-woocommerce' ); ?></span>
        </a>
        <a href="<?php echo esc_url( $urlOrderStatus ); ?>" class="nav-tab <?= $tab === 'order_status' ? 'nav-tab-active' : ''; ?>">
            <span><?php esc_html_e( 'Order status', 'license-manager-for-woocommerce' ); ?></span>
        </a>
        <a href="<?php echo esc_url( $urlRestApi ); ?>" class="nav-tab <?= $tab === 'rest_api' ? 'nav-tab-active' : ''; ?>">
            <span><?php esc_html_e( 'REST API keys', 'license-manager-for-woocommerce' ); ?></span>
        </a>
        <a href="<?php echo esc_url( $urlTools ); ?>" class="nav-tab <?= $tab === 'tools' ? 'nav-tab-active' : ''; ?>">
            <span><?php esc_html_e( 'Tools', 'license-manager-for-woocommerce' ); ?></span>
        </a>
    </nav>

	<?php if ( $tab == 'general' ): ?>

        <form action="<?php echo admin_url( 'options.php' ); ?>" method="POST">
			<?php settings_fields( 'lmfwc_settings_group_general' ); ?>
			<?php do_settings_sections( 'lmfwc_license_keys' ); ?>
			<?php do_settings_sections( 'lmfwc_my_account' ); ?>
			<?php do_settings_sections( 'lmfwc_rest_api' ); ?>
			<?php submit_button(); ?>
        </form>

	<?php elseif ( $tab === 'order_status' ): ?>

        <form action="<?php echo admin_url( 'options.php' ); ?>" method="POST">
			<?php settings_fields( 'lmfwc_settings_group_order_status' ); ?>
			<?php do_settings_sections( 'lmfwc_license_key_delivery' ); ?>
			<?php submit_button(); ?>
        </form>

	<?php elseif ( $tab === 'rest_api' ): ?>

		<?php if ( $action === 'list' ): ?>

			<?php include_once 'settings/rest-api-list.php'; ?>

		<?php elseif ( $action === 'show' ): ?>

			<?php include_once 'settings/rest-api-show.php'; ?>

		<?php else: ?>

			<?php include_once 'settings/rest-api-key.php'; ?>

		<?php endif; ?>

	<?php elseif ( $tab === 'tools' ): ?>

        <form action="<?php echo admin_url( 'options.php' ); ?>" method="POST">
			<?php settings_fields( 'lmfwc_settings_group_tools' ); ?>
			<?php do_settings_sections( 'lmfwc_export' ); ?>
			<?php submit_button(); ?>
        </form>

	<?php endif; ?>

</div>
