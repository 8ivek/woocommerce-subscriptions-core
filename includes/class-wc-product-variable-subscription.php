<?php
/**
 * Variable Subscription Product Class
 *
 * This class extends the WC Variable product class to create variable products with recurring payments.
 *
 * @class 		WC_Product_Variable_Subscription
 * @package		WooCommerce Subscriptions
 * @category	Class
 * @since		1.3
 *
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Product_Variable_Subscription extends WC_Product_Variable {

	private $min_max_variation_data = array();

	private $sorted_variation_prices = array();

	/**
	 * Get internal type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'variable-subscription';
	}

	/**
	 * Auto-load in-accessible properties on demand.
	 *
	 * @param mixed $key
	 * @return mixed
	 */
	public function __get( $key ) {

		if ( WC_Subscriptions::is_woocommerce_pre( '3.0' ) ) {
			$value = parent::__get( $key );
		} else {
			$value = wcs_product_deprecated_property_handler( $key, $this );

			// No matching property found in wcs_product_deprecated_property_handler()
			if ( is_null( $value ) ) {
				$value = parent::__get( $key );
			}
		}

		return $value;
	}

	/**
	 * Get the add to cart button text for the single page
	 *
	 * @access public
	 * @return string
	 */
	public function single_add_to_cart_text() {

		if ( $this->is_purchasable() && $this->is_in_stock() ) {
			$text = get_option( WC_Subscriptions_Admin::$option_prefix . '_add_to_cart_button_text', __( 'Sign Up Now', 'woocommerce-subscriptions' ) );
		} else {
			$text = parent::add_to_cart_text(); // translated "Read More"
		}

		return apply_filters( 'woocommerce_product_single_add_to_cart_text', $text, $this );
	}

	/**
	 * Returns the price in html format.
	 *
	 * @access public
	 * @param string $price (default: '')
	 * @return string
	 */
	public function get_price_html( $price = '' ) {

		$prices = $this->get_variation_prices( true );

		if ( empty( $prices['price'] ) ) {
			return apply_filters( 'woocommerce_variable_empty_price_html', '', $this );
		}

		$tax_display_mode = get_option( 'woocommerce_tax_display_shop' );

		$price = WC_Subscriptions_Product::get_price( $this->get_meta( '_min_price_variation_id' ) );
		$price = 'incl' == $tax_display_mode ? wcs_get_price_including_tax( $this, array( 'price' => $price ) ) : wcs_get_price_excluding_tax( $this, array( 'price' => $price ) );
		$price = $this->get_price_prefix( $prices ) . wc_price( $price ) . $this->get_price_suffix();
		$price = apply_filters( 'woocommerce_variable_price_html', $price, $this );
		$price = WC_Subscriptions_Product::get_price_string( $this, array( 'price' => $price ) );

		return apply_filters( 'woocommerce_variable_subscription_price_html', apply_filters( 'woocommerce_get_price_html', $price, $this ), $this );
	}

	/**
	 * Checks if the store manager has requested the current product be limited to one purchase
	 * per customer, and if so, checks whether the customer already has an active subscription to
	 * the product.
	 *
	 * @access public
	 * @return bool
	 */
	function is_purchasable() {
		$purchasable = WCS_Limiter::is_purchasable( parent::is_purchasable(), $this );

		return apply_filters( 'woocommerce_subscription_is_purchasable', $purchasable, $this );
	}

	/**
	 * Checks the product type to see if it is either this product's type or the parent's
	 * product type.
	 *
	 * @access public
	 * @param mixed $type Array or string of types
	 * @return bool
	 */
	public function is_type( $type ) {
		if ( 'variable' == $type || ( is_array( $type ) && in_array( 'variable', $type ) ) ) {
			return true;
		} else {
			return parent::is_type( $type );
		}
	}

	/**
	 * Sort an associative array of $variation_id => $price pairs in order of min and max prices.
	 *
	 * @param array $prices Associative array of $variation_id => $price pairs
	 * @return array
	 */
	protected function sort_variation_prices( $prices ) {

		// If we don't have any prices, there's nothing to sort.
		if ( empty( $prices ) ) {
			return $prices;
		}

		$prices_hash = md5( json_encode( $prices ) );

		if ( empty( $this->sorted_variation_prices[ $prices_hash ] ) ) {

			$min_max_variation_data = $this->get_min_and_max_variation_data( array_keys( $prices ) );

			$min_variation_id = $min_max_variation_data['min']['variation_id'];
			$max_variation_id = $min_max_variation_data['max']['variation_id'];

			// Reorder the variable price arrays to reflect the min and max values so that WooCommerce will find them in the correct order
			$min_price = $prices[ $min_variation_id ];
			$max_price = $prices[ $max_variation_id ];

			unset( $prices[ $min_variation_id ] );
			unset( $prices[ $max_variation_id ] );

			// Prepend the minimum variation and append the maximum variation
			$prices  = array( $min_variation_id => $min_price ) + $prices;
			$prices += array( $max_variation_id => $max_price );

			$this->sorted_variation_prices[ $prices_hash ] = $prices;
		}

		return $this->sorted_variation_prices[ $prices_hash ];
	}

	/**
	 * Set the product's min and max variation data.
	 *
	 * @param array $min_and_max_data The min and max variation data returned by @see wcs_get_min_max_variation_data(). Optional.
	 * @param array $variation_ids The child variation IDs. Optional. By default this value be generated by @see WC_Product_Variable->get_visible_children().
	 * @since 2.3.0
	 */
	public function set_min_and_max_variation_data( $min_and_max_data = array(), $variation_ids = array() ) {

		if ( empty( $variation_ids ) ) {
			$variation_ids = $this->get_visible_children();
		}

		if ( empty( $min_and_max_data ) ) {
			$min_and_max_data = wcs_get_min_max_variation_data( $this, $variation_ids );
		}

		// Sort the variation IDs so the hash isn't different for the same array of IDs
		sort( $variation_ids );
		$this->add_meta_data( '_min_max_variation_data', $min_and_max_data, true );
		$this->add_meta_data( '_min_max_variation_ids_hash', md5( json_encode( $variation_ids ) ), true );
	}

	/**
	 * Get the min and max variation data.
	 *
	 * This is a wrapper for @see wcs_get_min_max_variation_data() but to avoid calling
	 * that resource intensive function multiple times per request, check the value
	 * stored in meta or cached in memory before calling that function.
	 *
	 * @param  array $variation_ids An array of variation IDs.
	 * @return array The variable product's min and max variation data.
	 * @since 2.3.0
	 */
	public function get_min_and_max_variation_data( $variation_ids ) {
		// Sort the variation IDs so the hash isn't different for the same array of IDs
		sort( $variation_ids );
		$variation_ids_hash = md5( json_encode( $variation_ids ) );

		// If this variable product has no min and max variation data, set it.
		if ( ! $this->meta_exists( '_min_max_variation_ids_hash' ) ) {
			$this->set_min_and_max_variation_data();
			$this->save();
		}

		if ( $variation_ids_hash === $this->get_meta( '_min_max_variation_ids_hash', true ) ) {
			$min_and_max_variation_data = $this->get_meta( '_min_max_variation_data', true );
		} elseif ( ! empty( $this->min_max_variation_data[ $variation_ids_hash ] ) ) {
			$min_and_max_variation_data = $this->min_max_variation_data[ $variation_ids_hash ];
		} else {
			$min_and_max_variation_data = wcs_get_min_max_variation_data( $this, $variation_ids );
			$this->min_max_variation_data[ $variation_ids_hash ] = $min_and_max_variation_data;
		}

		return $min_and_max_variation_data;
	}

	/* Deprecated Functions */

	/**
	 * Return the sign-up fee for this product
	 *
	 * @return string
	 */
	public function get_sign_up_fee() {
		wcs_deprecated_function( __METHOD__, '2.2.0', 'WC_Subscriptions_Product::get_sign_up_fee( $this )' );
		return WC_Subscriptions_Product::get_sign_up_fee( $this );
	}

	/**
	 * Returns the sign up fee (including tax) by filtering the products price used in
	 * @see WC_Product::get_price_including_tax( $qty )
	 *
	 * @return string
	 */
	public function get_sign_up_fee_including_tax( $qty = 1 ) {
		wcs_deprecated_function( __METHOD__, '2.2.0', 'wcs_get_price_including_tax( $product, array( "qty" => $qty, "price" => WC_Subscriptions_Product::get_sign_up_fee( $product ) ) )' );
		return wcs_get_price_including_tax( $this, array( 'qty' => $qty, 'price' => WC_Subscriptions_Product::get_sign_up_fee( $this ) ) );
	}

	/**
	 * Returns the sign up fee (excluding tax) by filtering the products price used in
	 * @see WC_Product::get_price_excluding_tax( $qty )
	 *
	 * @return string
	 */
	public function get_sign_up_fee_excluding_tax( $qty = 1 ) {
		wcs_deprecated_function( __METHOD__, '2.2.0', 'wcs_get_price_excluding_tax( $product, array( "qty" => $qty, "price" => WC_Subscriptions_Product::get_sign_up_fee( $product ) ) )' );
		return wcs_get_price_excluding_tax( $this, array( 'qty' => $qty, 'price' => WC_Subscriptions_Product::get_sign_up_fee( $this ) ) );
	}

	/**
	 *
	 * @param string $product_type A string representation of a product type
	 */
	public function add_to_cart_handler( $handler, $product ) {
		wcs_deprecated_function( __METHOD__, '2.2.0', 'WC_Subscriptions_Cart::add_to_cart_handler( $handler, $product )' );
		return WC_Subscriptions_Cart::add_to_cart_handler( $handler, $product );
	}

	/**
	 * Sync variable product prices with the childs lowest/highest prices.
	 *
	 * @access public
	 * @return void
	 */
	public function variable_product_sync( $product_id = '' ) {
		wcs_deprecated_function( __METHOD__, '2.2,0', 'WC_Subscriptions_Product::variable_subscription_product_sync( $this )' );

		if ( empty( $product_id ) ) {
			$product_id = $this->get_id();
		}

		// Sync prices with children
		self::sync( $product_id );
	}

	/**
	 * Get the suffix to display before prices.
	 *
	 * @return string
	 */
	protected function get_price_prefix( $prices ) {

		$child_variation_ids    = array_keys( $prices['price'] );
		$min_max_variation_data = $this->get_min_and_max_variation_data( $child_variation_ids );

		// Are the subscription details of all variations identical?
		if ( $min_max_variation_data['identical'] ) {
			$prefix = '';
		} else {
			$prefix = wcs_get_price_html_from_text( $this );
		}

		return $prefix;
	}
}
