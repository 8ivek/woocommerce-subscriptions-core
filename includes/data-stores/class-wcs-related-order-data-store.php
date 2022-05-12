<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Related order data store for orders.
 *
 * Importantly, this class uses WC_Data API methods, like WC_Data::add_meta_data() and WC_Data::get_meta(), to manage the
 * relationships instead of add_post_meta() or get_post_meta(). This ensures that the relationship is stored, regardless
 * of the order data store being used.
 *
 * @version 2.0.0
 */
class WCS_Related_Order_Data_Store extends WCS_Related_Order_Store {

	/**
	 * Meta keys used to link an order with a subscription for each type of relationship.
	 *
	 * @since 2.0.0
	 * @var array $meta_keys Relationship => Meta key
	 */
	private $meta_keys;

	/**
	 * Constructor: sets meta keys used for storing each order relation.
	 */
	public function __construct() {
		foreach ( $this->get_relation_types() as $relation_type ) {
			$this->meta_keys[ $relation_type ] = sprintf( '_subscription_%s', $relation_type );
		}
	}

	/**
	 * Find orders related to a given subscription in a given way.
	 *
	 * @since 2.0.0
	 *
	 * @param WC_Order $subscription  The ID of the subscription for which calling code wants the related orders.
	 * @param string   $relation_type The relationship between the subscription and the orders. Must be 'renewal', 'switch' or 'resubscribe.
	 *
	 * @return array
	 */
	public function get_related_order_ids( WC_Order $subscription, $relation_type ) {
		$order_relation_query_var_handler = function( $query, $query_vars ) use ( $subscription, $relation_type ) {
			if ( ! empty( $query_vars['_related_orders_by_relation_type'] ) ) {
				$query['meta_query'][] = array(
					'key'     => $this->get_meta_key( $relation_type ),
					'compare' => '=',
					'value'   => $subscription->get_id(), // We can't rely on get_id() being available here, because we only require a WC_Order, not a WC_Subscription, and WC_Order does not have get_id() available with WC < 3.0
					'type'    => 'numeric',
				);
				unset( $query_vars['_related_orders_by_relation_type'] );
			}

			return $query;
		};

		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $order_relation_query_var_handler, 10, 2 );

		$related_order_ids = wc_get_orders(
			array(
				'limit'                            => -1,
				'status'                           => 'any',
				'return'                           => 'ids',
				'orderby'                          => 'ID',
				'order'                            => 'DESC',
				'_related_orders_by_relation_type' => true,
			)
		);

		remove_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $order_relation_query_var_handler, 10 );

		return $related_order_ids;
	}

	/**
	 * Find subscriptions related to a given order in a given way, if any.
	 *
	 * @since 2.0.0
	 *
	 * @param WC_Order $order         The ID of an order that may be linked with subscriptions.
	 * @param string   $relation_type The relationship between the subscription and the orders. Must be 'renewal', 'switch' or 'resubscribe.
	 *
	 * @return array
	 */
	public function get_related_subscription_ids( WC_Order $order, $relation_type ) {
		$related_order_meta_key = $this->get_meta_key( $relation_type );

		$related_subscription_ids = $order->get_meta( $related_order_meta_key, false );
		// Normalise the return value: WooCommerce returns a set of WC_Meta_Data objects, with values cast to strings, even if they're integers
		$related_subscription_ids = wp_list_pluck( $related_subscription_ids, 'value' );
		$related_subscription_ids = array_map( 'absint', $related_subscription_ids );
		$related_subscription_ids = array_values( $related_subscription_ids );

		return apply_filters( 'wcs_orders_related_subscription_ids', $related_subscription_ids, $order, $relation_type );
	}

	/**
	 * Helper function for linking an order to a subscription via a given relationship.
	 *
	 * Existing order relationships of the same type will not be overwritten. This only adds a relationship. To overwrite,
	 * you must also remove any existing relationship with @see $this->delete_relation().
	 *
	 * @since 2.0.0
	 *
	 * @param WC_Order $order         The order to link with the subscription.
	 * @param WC_Order $subscription  The order or subscription to link the order to.
	 * @param string   $relation_type The relationship between the subscription and the order. Must be 'renewal', 'switch' or 'resubscribe' unless custom relationships are implemented.
	 *
	 * @return void
	 */
	public function add_relation( WC_Order $order, WC_Order $subscription, $relation_type ) {
		// We can't rely on $subscription->get_id() being available here, because we only require a WC_Order, not a WC_Subscription, and WC_Order does not have get_id() available with WC < 3.0
		$subscription_id        = wcs_get_objects_property( $subscription, 'id' );
		$related_order_meta_key = $this->get_meta_key( $relation_type );

		// We want to allow more than one piece of meta per key on the order, but we don't want to duplicate the same meta key => value combination, so we need to check if it is set first
		$existing_relations   = $order->get_meta( $related_order_meta_key, false );
		$existing_related_ids = wp_list_pluck( $existing_relations, 'value' );

		if ( empty( $existing_relations ) || ! in_array( $subscription_id, $existing_related_ids, true ) ) {
			$order->add_meta_data( $related_order_meta_key, $subscription_id, false );
			$order->save_meta_data();
		}
	}

	/**
	 * Remove the relationship between a given order and subscription.
	 *
	 * This data store links the relationship for a renewal order and a subscription in meta data against the order.
	 *
	 * @since 2.0.0
	 *
	 * @param WC_Order $order         An order that may be linked with subscriptions.
	 * @param WC_Order $subscription  A subscription or order to unlink the order with, if a relation exists.
	 * @param string   $relation_type The relationship between the subscription and the order. Must be 'renewal', 'switch' or 'resubscribe' unless custom relationships are implemented.
	 *
	 * @return void
	 */
	public function delete_relation( WC_Order $order, WC_Order $subscription, $relation_type ) {
		$related_order_meta_key = $this->get_meta_key( $relation_type );

		foreach ( $order->get_meta_data() as $meta ) {
			if ( $related_order_meta_key === $meta->key && wcs_get_objects_property( $subscription, 'id' ) === (int) $meta->value ) { // we can't do strict comparison here, because WC_Meta_Data casts the subscription ID to be a string
				$order->delete_meta_data_by_mid( $meta->id );
			}
		}

		$order->save_meta_data();
	}

	/**
	 * Remove all related orders/subscriptions of a given type from an order.
	 *
	 * @since 2.0.0
	 *
	 * @param WC_Order $order         An order that may be linked with subscriptions.
	 * @param string   $relation_type The relationship between the subscription and the order. Must be 'renewal', 'switch' or 'resubscribe' unless custom relationships are implemented.
	 *
	 * @return void
	 */
	public function delete_relations( WC_Order $order, $relation_type ) {
		$related_order_meta_key = $this->get_meta_key( $relation_type );
		$order->delete_meta_data( $related_order_meta_key );
		$order->save_meta_data();
	}

	/**
	 * Get the meta keys used to link orders with subscriptions.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_meta_keys() {
		return $this->meta_keys;
	}

	/**
	 * Get the meta key used to link an order with a subscription based on the type of relationship.
	 *
	 * @since 2.0.0
	 *
	 * @param string $relation_type   The order's relationship with the subscription. Must be 'renewal', 'switch' or 'resubscribe'.
	 * @param string $prefix_meta_key Whether to add the underscore prefix to the meta key or not. 'prefix' to prefix the key. 'do_not_prefix' to not prefix the key.
	 *
	 * @return string
	 */
	protected function get_meta_key( $relation_type, $prefix_meta_key = 'prefix' ) {
		$this->check_relation_type( $relation_type );

		$meta_key = $this->meta_keys[ $relation_type ];

		if ( 'do_not_prefix' === $prefix_meta_key ) {
			$meta_key = wcs_maybe_unprefix_key( $meta_key );
		}

		return $meta_key;
	}
}