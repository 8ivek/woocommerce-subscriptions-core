<?php
/**
 * WooCommerce Subscriptions Extend Store API.
 *
 * A class to extend the store public API with subscription related data
 * for each subscription item
 *
 * @package WooCommerce Subscriptions
 * @author  WooCommerce
 * @since   3.0.9
 */

use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\ExtendRestApi;
use Automattic\WooCommerce\Blocks\StoreApi\Schemas\CartItemSchema;

class WC_Subscriptions_Extend_Store_Endpoint {

	/**
	 * Stores Rest Extending instance
	 *
	 * @var ExtendRestApi
	 */
	private static $extend;

	/**
	 * Plugin Identifier
	 *
	 * @var string
	 */
	const IDENTIFIER = 'subscriptions';

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * @since 3.0.9
	 */
	public static function init() {

		self::$extend = Package::container()->get( ExtendRestApi::class );
		self::extend_store();
	}

	public static function extend_store() {

		self::$extend->register_endpoint_data( [
			'endpoint'  => CartItemSchema::IDENTIFIER,
			'namespace' => self::IDENTIFIER,
			'data_callback' => array( 'WC_Subscriptions_Extend_Store_Endpoint', 'extend_cart_item_data' ),
			'schema_callback' => array( 'WC_Subscriptions_Extend_Store_Endpoint', 'extend_cart_item_schema' )
		] );
	}

	public static function extend_cart_item_data( $cart_item ) {

		$product = $cart_item['data'];
		$item_data = [];

		if ( $product->get_type() === 'subscription' ) {
			$item_data = [
				"billing_period"      => WC_Subscriptions_Product::get_period( $product ),
				"billing_interval"    => (int) WC_Subscriptions_Product::get_interval( $product ),
				"subscription_length" => (int) WC_Subscriptions_Product::get_length( $product ),
				"trial_length"        => (int) WC_Subscriptions_Product::get_trial_length( $product ),
				"trial_period"        => WC_Subscriptions_Product::get_trial_period( $product ),
				"sign_up_fees"         => self::prepare_money_response( WC_Subscriptions_Product::get_sign_up_fee( $product ), wc_get_price_decimals() ),
			];
		}

		return $item_data;
	}

	public static function extend_cart_item_schema() {
		return [
			'billing_period'            => [
				'description' => __( 'Billing period for the subscription.', 'woocommerce-subscriptions' ),
				'type'        => 'string',
				'enum'        => array_keys( wcs_get_subscription_period_strings() ),
				'context'     => array( 'view', 'edit' ),
			],
			'billing_interval'          => [
				'description' => __( 'The number of billing periods between subscription renewals.', 'woocommerce-subscriptions' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
			],
			"subscription_length"          => [
				'description' => __( 'Subscription Product length.', 'woocommerce-subscriptions' ),
				'type'        => 'integer',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			"trial_period"    => [
				'description' => __( 'Subscription Product trial period.', 'woocommerce-subscriptions' ),
				'type'        => 'string',
				'enum'        => array_keys( wcs_get_subscription_period_strings() ),
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			"trial_length"    => [
				'description' => __( 'Subscription Product trial interval.', 'woocommerce-subscriptions' ),
				'type'        => 'integer',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			"sign_up_fees"            => [
				'description' => __( 'Subscription Product Signup fees.', 'woocommerce-subscriptions' ),
				'type'        => 'string',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
		];
	}

	/**
	 * Convert monetary values from WooCommerce to string based integers, using
	 * the smallest unit of a currency.
	 *
	 * TODO: This function is copied from WooCommerce Blocks, remove it once https://github.com/woocommerce/woocommerce-gutenberg-products-block/issues/3264 is closed.
	 *
	 * @param string|float $amount Monetary amount with decimals.
	 * @param int          $decimals Number of decimals the amount is formatted with.
	 * @param int          $rounding_mode Defaults to the PHP_ROUND_HALF_UP constant.
	 * @return string      The new amount.
	 */
	protected static function prepare_money_response( $amount, $decimals = 2, $rounding_mode = PHP_ROUND_HALF_UP ) {
		return (string) intval(
			round(
				( (float) wc_format_decimal( $amount ) ) * ( 10 ** $decimals ),
				0,
				absint( $rounding_mode )
			)
		);
	}
}
