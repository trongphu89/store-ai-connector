<?php
/**
 * SAC Orders - WooCommerce order management via REST API.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SAC_Orders {

	/**
	 * List orders with filters and pagination.
	 */
	public static function get_orders( WP_REST_Request $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'WooCommerce not active' ) );
		}

		$status       = sanitize_text_field( $request->get_param( 'status' ) ?: 'any' );
		$per_page     = min( absint( $request->get_param( 'per_page' ) ?: 20 ), 200 );
		$page         = max( absint( $request->get_param( 'page' ) ?: 1 ), 1 );
		$date_after   = sanitize_text_field( $request->get_param( 'date_after' ) ?: '' );
		$date_before  = sanitize_text_field( $request->get_param( 'date_before' ) ?: '' );
		$search       = sanitize_text_field( $request->get_param( 'search' ) ?: '' );

		$args = array(
			'limit'    => $per_page,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
		);

		$query_status = self::normalize_query_status( $status );
		if ( ! empty( $query_status ) ) {
			$args['status'] = $query_status;
		}

		if ( $date_after && $date_before ) {
			$args['date_created'] = $date_after . '...' . $date_before;
		} elseif ( $date_after ) {
			$args['date_created'] = '>=' . $date_after;
		} elseif ( $date_before ) {
			$args['date_created'] = '<=' . $date_before;
		}

		if ( $search ) {
			$args['s'] = $search;
		}

		$result = wc_get_orders( $args );

		$orders = array();
		foreach ( $result->orders as $order ) {
			$orders[] = self::format_order( $order );
		}

		return rest_ensure_response( array(
			'success' => true,
			'data'    => $orders,
			'meta'    => array(
				'total'       => (int) $result->total,
				'total_pages' => (int) $result->max_num_pages,
				'page'        => $page,
				'per_page'    => $per_page,
			),
		) );
	}

	/**
	 * Get a single order by ID.
	 */
	public static function get_order( WP_REST_Request $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'WooCommerce not active' ) );
		}

		$order_id = absint( $request->get_param( 'id' ) );
		if ( ! $order_id ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'Order ID is required' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'Order not found' ) );
		}

		return rest_ensure_response( array(
			'success' => true,
			'data'    => self::format_order( $order, true ),
		) );
	}

	/**
	 * Update order status.
	 */
	public static function update_order_status( WP_REST_Request $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'WooCommerce not active' ) );
		}

		$params          = $request->get_json_params();
		$order_id        = absint( $params['order_id'] ?? 0 );
		$status          = sanitize_text_field( $params['status'] ?? '' );
		$note            = isset( $params['note'] ) ? wp_kses_post( $params['note'] ) : '';
		$notify_customer = ! empty( $params['notify_customer'] );

		if ( ! $order_id ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'Order ID is required' ) );
		}

		if ( ! $status ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'Status is required' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'Order not found' ) );
		}

		$old_status = $order->get_status();
		$new_status = self::normalize_set_status( $status );

		$order->update_status( $new_status, $note, $notify_customer );

		return rest_ensure_response( array(
			'success'    => true,
			'order_id'   => $order_id,
			'old_status' => $old_status,
			'new_status' => $new_status,
		) );
	}

	/**
	 * Add tracking information to an order.
	 */
	public static function add_tracking( WP_REST_Request $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'WooCommerce not active' ) );
		}

		$params          = $request->get_json_params();
		$order_id        = absint( $params['order_id'] ?? 0 );
		$tracking_number = sanitize_text_field( $params['tracking_number'] ?? '' );
		$carrier         = sanitize_text_field( $params['carrier'] ?? '' );
		$tracking_url    = esc_url_raw( $params['tracking_url'] ?? '' );

		if ( ! $order_id ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'Order ID is required' ) );
		}

		if ( ! $tracking_number ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'Tracking number is required' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'Order not found' ) );
		}

		$order->update_meta_data( '_tracking_number', $tracking_number );
		if ( $carrier ) {
			$order->update_meta_data( '_tracking_carrier', $carrier );
		}
		if ( $tracking_url ) {
			$order->update_meta_data( '_tracking_url', $tracking_url );
		}
		$order->save();

		$note_parts = array( 'Tracking added: ' . $tracking_number );
		if ( $carrier ) {
			$note_parts[] = 'Carrier: ' . $carrier;
		}
		if ( $tracking_url ) {
			$note_parts[] = 'URL: ' . $tracking_url;
		}
		$order->add_order_note( implode( ' | ', $note_parts ), true );

		return rest_ensure_response( array(
			'success'         => true,
			'order_id'        => $order_id,
			'tracking_number' => $tracking_number,
			'carrier'         => $carrier,
			'tracking_url'    => $tracking_url,
		) );
	}

	/**
	 * Get order statistics for a time period.
	 */
	public static function get_order_stats( WP_REST_Request $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'WooCommerce not active' ) );
		}

		$period      = sanitize_text_field( $request->get_param( 'period' ) ?: 'today' );
		$date_after  = sanitize_text_field( $request->get_param( 'date_after' ) ?: '' );
		$date_before = sanitize_text_field( $request->get_param( 'date_before' ) ?: '' );

		$range = self::resolve_period_range( $period, $date_after, $date_before );
		if ( is_wp_error( $range ) ) {
			return rest_ensure_response( array( 'success' => false, 'error' => $range->get_error_message() ) );
		}

		$args = array(
			'limit'        => -1,
			'status'       => array_keys( wc_get_order_statuses() ),
			'date_created' => $range['after'] . '...' . $range['before'],
			'return'       => 'objects',
		);

		$orders = wc_get_orders( $args );

		$total_orders    = count( $orders );
		$total_revenue   = 0.0;
		$by_status       = array();
		$daily_revenue   = array();
		$product_totals  = array();

		foreach ( $orders as $order ) {
			$order_total = (float) $order->get_total();
			$status      = $order->get_status();

			$total_revenue += $order_total;

			if ( ! isset( $by_status[ $status ] ) ) {
				$by_status[ $status ] = array(
					'count'   => 0,
					'revenue' => 0.0,
				);
			}
			$by_status[ $status ]['count']++;
			$by_status[ $status ]['revenue'] += $order_total;

			$date_key = $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : '';
			if ( $date_key ) {
				if ( ! isset( $daily_revenue[ $date_key ] ) ) {
					$daily_revenue[ $date_key ] = 0.0;
				}
				$daily_revenue[ $date_key ] += $order_total;
			}

			foreach ( $order->get_items() as $item ) {
				$product_id = $item->get_product_id();
				$name       = $item->get_name();
				$key        = $product_id ? (string) $product_id : sanitize_title( $name );

				if ( ! isset( $product_totals[ $key ] ) ) {
					$product_totals[ $key ] = array(
						'product_id' => $product_id,
						'name'       => $name,
						'quantity'   => 0,
						'revenue'    => 0.0,
					);
				}
				$product_totals[ $key ]['quantity'] += (int) $item->get_quantity();
				$product_totals[ $key ]['revenue']  += (float) $item->get_total();
			}
		}

		uasort( $product_totals, function ( $a, $b ) {
			return $b['revenue'] <=> $a['revenue'];
		} );
		$top_products = array_values( array_slice( $product_totals, 0, 10 ) );

		$avg_order_value = $total_orders > 0 ? round( $total_revenue / $total_orders, 2 ) : 0.0;

		ksort( $daily_revenue );

		return rest_ensure_response( array(
			'success'         => true,
			'total_orders'    => $total_orders,
			'total_revenue'   => round( $total_revenue, 2 ),
			'avg_order_value' => $avg_order_value,
			'by_status'       => $by_status,
			'daily_revenue'   => $daily_revenue,
			'top_products'    => $top_products,
			'period'          => array(
				'type'        => $period,
				'date_after'  => $range['after'],
				'date_before' => $range['before'],
			),
		) );
	}

	/**
	 * Bulk update multiple orders.
	 */
	public static function bulk_update_orders( WP_REST_Request $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'WooCommerce not active' ) );
		}

		$params = $request->get_json_params();
		$orders = isset( $params['orders'] ) && is_array( $params['orders'] ) ? $params['orders'] : array();

		if ( empty( $orders ) ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'No orders provided' ) );
		}

		$results       = array();
		$success_count = 0;
		$fail_count    = 0;

		foreach ( $orders as $entry ) {
			$order_id = absint( $entry['id'] ?? 0 );
			if ( ! $order_id ) {
				$results[] = array(
					'id'      => 0,
					'success' => false,
					'error'   => 'Order ID is required',
				);
				$fail_count++;
				continue;
			}

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				$results[] = array(
					'id'      => $order_id,
					'success' => false,
					'error'   => 'Order not found',
				);
				$fail_count++;
				continue;
			}

			$result = array(
				'id'      => $order_id,
				'success' => true,
			);

			if ( ! empty( $entry['status'] ) ) {
				$old_status         = $order->get_status();
				$new_status         = self::normalize_set_status( $entry['status'] );
				$notify             = ! empty( $entry['notify'] );
				$status_note        = isset( $entry['note'] ) ? wp_kses_post( $entry['note'] ) : '';
				$order->update_status( $new_status, $status_note, $notify );
				$result['old_status'] = $old_status;
				$result['new_status'] = $new_status;
			} elseif ( ! empty( $entry['note'] ) ) {
				$order->add_order_note( wp_kses_post( $entry['note'] ), ! empty( $entry['notify'] ) );
			}

			if ( ! empty( $entry['tracking_number'] ) ) {
				$tracking_number = sanitize_text_field( $entry['tracking_number'] );
				$carrier         = sanitize_text_field( $entry['carrier'] ?? '' );

				$order->update_meta_data( '_tracking_number', $tracking_number );
				if ( $carrier ) {
					$order->update_meta_data( '_tracking_carrier', $carrier );
				}
				$order->save();

				$tracking_note = 'Tracking added: ' . $tracking_number;
				if ( $carrier ) {
					$tracking_note .= ' | Carrier: ' . $carrier;
				}
				$order->add_order_note( $tracking_note, true );

				$result['tracking_number'] = $tracking_number;
				$result['carrier']         = $carrier;
			}

			$results[] = $result;
			$success_count++;
		}

		return rest_ensure_response( array(
			'success'       => true,
			'results'       => $results,
			'total'         => count( $orders ),
			'success_count' => $success_count,
			'fail_count'    => $fail_count,
		) );
	}

	/**
	 * Get WooCommerce default page IDs.
	 */
	public static function get_woo_page_ids( WP_REST_Request $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return rest_ensure_response( array( 'success' => false, 'error' => 'WooCommerce not active' ) );
		}

		return rest_ensure_response( array(
			'success'           => true,
			'shop_page_id'      => absint( get_option( 'woocommerce_shop_page_id' ) ),
			'cart_page_id'      => absint( get_option( 'woocommerce_cart_page_id' ) ),
			'checkout_page_id'  => absint( get_option( 'woocommerce_checkout_page_id' ) ),
			'myaccount_page_id' => absint( get_option( 'woocommerce_myaccount_page_id' ) ),
			'terms_page_id'     => absint( get_option( 'woocommerce_terms_page_id' ) ),
		) );
	}

	/**
	 * Format order object for API response.
	 */
	private static function format_order( $order, bool $with_notes = false ): array {
		$billing_first = $order->get_billing_first_name();
		$billing_last  = $order->get_billing_last_name();
		$customer_name = trim( $billing_first . ' ' . $billing_last );

		$items = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$product   = $item->get_product();
			$image_url = '';
			if ( $product ) {
				$image_id = $product->get_image_id();
				if ( $image_id ) {
					$image_url = wp_get_attachment_url( $image_id ) ?: '';
				}
			}

			$items[] = array(
				'item_id'      => (int) $item_id,
				'product_id'   => (int) $item->get_product_id(),
				'variation_id' => (int) $item->get_variation_id(),
				'name'         => $item->get_name(),
				'quantity'     => (int) $item->get_quantity(),
				'total'        => (float) $item->get_total(),
				'sku'          => $product ? ( $product->get_sku() ?: '' ) : '',
				'image_url'    => $image_url,
			);
		}

		$data = array(
			'id'               => $order->get_id(),
			'status'           => $order->get_status(),
			'date_created'     => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null,
			'date_modified'    => $order->get_date_modified() ? $order->get_date_modified()->date( 'c' ) : null,
			'total'            => (float) $order->get_total(),
			'subtotal'         => (float) $order->get_subtotal(),
			'shipping_total'   => (float) $order->get_shipping_total(),
			'currency'         => $order->get_currency(),
			'customer_name'    => $customer_name,
			'customer_email'   => $order->get_billing_email(),
			'customer_phone'   => $order->get_billing_phone(),
			'billing_address'  => self::format_address( $order, 'billing' ),
			'shipping_address' => self::format_address( $order, 'shipping' ),
			'payment_method'   => $order->get_payment_method_title(),
			'transaction_id'   => $order->get_transaction_id(),
			'customer_note'    => $order->get_customer_note(),
			'tracking_number'  => $order->get_meta( '_tracking_number', true ),
			'tracking_carrier' => $order->get_meta( '_tracking_carrier', true ),
			'tracking_url'     => $order->get_meta( '_tracking_url', true ),
			'items'            => $items,
		);

		if ( $with_notes ) {
			$notes = array();
			foreach ( $order->get_order_notes() as $note ) {
				$notes[] = array(
					'id'             => (int) $note->id,
					'content'        => $note->content,
					'date_created'   => $note->date_created->date( 'c' ),
					'customer_note'  => (bool) $note->customer_note,
					'added_by'       => $note->added_by,
				);
			}
			$data['notes'] = $notes;
		}

		return $data;
	}

	/**
	 * Normalize status for wc_get_orders query (wc- prefix).
	 */
	private static function normalize_query_status( $status ) {
		if ( 'any' === $status || '' === $status ) {
			return '';
		}

		if ( false !== strpos( $status, ',' ) ) {
			$statuses = array_map( 'trim', explode( ',', $status ) );
			$normalized = array();
			foreach ( $statuses as $single ) {
				if ( $single ) {
					$normalized[] = 0 === strpos( $single, 'wc-' ) ? $single : 'wc-' . $single;
				}
			}
			return $normalized;
		}

		return 0 === strpos( $status, 'wc-' ) ? $status : 'wc-' . $status;
	}

	/**
	 * Normalize status for update_status (no wc- prefix).
	 */
	private static function normalize_set_status( $status ) {
		$status = sanitize_text_field( $status );
		if ( 0 === strpos( $status, 'wc-' ) ) {
			$status = substr( $status, 3 );
		}
		return $status;
	}

	/**
	 * Resolve date range from period parameter.
	 */
	private static function resolve_period_range( $period, $date_after, $date_before ) {
		$tz  = wp_timezone();
		$now = new DateTime( 'now', $tz );

		switch ( $period ) {
			case 'today':
				$start = clone $now;
				$start->setTime( 0, 0, 0 );
				$end = clone $now;
				$end->setTime( 23, 59, 59 );
				break;

			case 'week':
				$start = clone $now;
				$start->modify( 'monday this week' )->setTime( 0, 0, 0 );
				$end = clone $now;
				$end->setTime( 23, 59, 59 );
				break;

			case 'month':
				$start = clone $now;
				$start->modify( 'first day of this month' )->setTime( 0, 0, 0 );
				$end = clone $now;
				$end->setTime( 23, 59, 59 );
				break;

			case 'year':
				$start = clone $now;
				$start->modify( 'first day of january this year' )->setTime( 0, 0, 0 );
				$end = clone $now;
				$end->setTime( 23, 59, 59 );
				break;

			case 'custom':
				if ( ! $date_after || ! $date_before ) {
					return new WP_Error( 'invalid_period', 'date_after and date_before are required for custom period' );
				}
				try {
					$start = new DateTime( $date_after, $tz );
					$end   = new DateTime( $date_before, $tz );
				} catch ( Exception $e ) {
					return new WP_Error( 'invalid_date', 'Invalid date format' );
				}
				$start->setTime( 0, 0, 0 );
				$end->setTime( 23, 59, 59 );
				break;

			default:
				return new WP_Error( 'invalid_period', 'Invalid period. Use today, week, month, year, or custom' );
		}

		return array(
			'after'  => $start->format( 'Y-m-d H:i:s' ),
			'before' => $end->format( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Format billing or shipping address.
	 */
	private static function format_address( $order, $type ) {
		if ( 'shipping' === $type ) {
			return array(
				'first_name' => $order->get_shipping_first_name(),
				'last_name'  => $order->get_shipping_last_name(),
				'company'    => $order->get_shipping_company(),
				'address_1'  => $order->get_shipping_address_1(),
				'address_2'  => $order->get_shipping_address_2(),
				'city'       => $order->get_shipping_city(),
				'state'      => $order->get_shipping_state(),
				'postcode'   => $order->get_shipping_postcode(),
				'country'    => $order->get_shipping_country(),
			);
		}

		return array(
			'first_name' => $order->get_billing_first_name(),
			'last_name'  => $order->get_billing_last_name(),
			'company'    => $order->get_billing_company(),
			'address_1'  => $order->get_billing_address_1(),
			'address_2'  => $order->get_billing_address_2(),
			'city'       => $order->get_billing_city(),
			'state'      => $order->get_billing_state(),
			'postcode'   => $order->get_billing_postcode(),
			'country'    => $order->get_billing_country(),
		);
	}
}
