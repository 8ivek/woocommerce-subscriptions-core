<?php
/**
 * Customer Notification: Subscription is about to expire email. Plain text version.
 *
 * @package WooCommerce_Subscriptions/Templates/Emails/Plain
 * @version x.x.x
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

echo esc_html( $email_heading . "\n\n" );

echo "\n\n";

echo esc_html(
	sprintf(
		/* translators: %s: Customer first name */
		__( 'Heads up, %s.', 'woocommerce-subscriptions' ),
		$subscription->get_billing_first_name()
	)
);

echo "\n\n";

echo esc_html(
	sprintf(
		// translators: %1$s: human readable time difference (eg 3 days, 1 day), %2$s: date in local format.
		__(
			'Your subscription expires in %1$s — that’s %2$s.',
			'woocommerce-subscriptions'
		),
		$subscription_time_til_event,
		$subscription_event_date
	)
);

echo "\n\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

esc_html_e( 'Here are the details:', 'woocommerce-subscriptions' );

// Show subscription details.
\WC_Subscriptions_Email::subscription_details( $subscription, $order, $sent_to_admin, $plain_text, true );

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
}

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );