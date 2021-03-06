<?php
/**
 * Checkout
 *
 * @since Astoundify Crowdfunding 0.9
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Track number of purchases for each pledge amount.
 *
 * @since Astoundify Crowdfunding 0.9
 *
 * @param int $payment the ID number of the payment
 * @param array $payment_data The payment data for the cart
 * @return void
 */
function atcf_log_pledge_limit( $payment_id, $new_status, $old_status ) {
	global $edd_logs;

	// If we're in test mode, don't proceed unless expected.
	if ( edd_is_test_mode() && ! apply_filters( 'edd_log_test_payment_stats', false ) ) {
		return;
	}

	// When shifting from pending status to something other than 
	// those two, we should reduce the backer count.
	if ( 'pending' == $old_status ) {

		// Make sure the backer count is only incremented when the payment
		// is completed or pre-approved.
		if ( in_array( $new_status, array( 'publish', 'preapproval' ) ) ) {

			atcf_update_backer_count( $payment_id, 'increase' );

		}
	}
	// When shifting from a publish/preapproved status to something other than 
	// those two, we should reduce the backer count.
	else if ( in_array( $old_status, array( 'publish', 'preapproval' ) ) ) {

		if ( ! in_array( $new_status,  array( 'publish', 'preapproval' ) ) ) {

			atcf_update_backer_count( $payment_id, 'decrease' );

		}
	}
}
add_action( 'edd_update_payment_status', 'atcf_log_pledge_limit', 100, 3 );

/**
 * Increment the backer count for every download in this payment. 
 *
 * @param 	int 		$payment_id
 * @param 	string 		$direction
 * @return 	void
 * @since 	0.9
 */
function atcf_update_backer_count( $payment_id, $direction ) {
	$payment_data = edd_get_payment_meta( $payment_id );
	$downloads    = maybe_unserialize( $payment_data[ 'downloads' ] );

	if ( ! is_array( $downloads ) ) {
		return;
	}

	foreach ( $downloads as $download ) {

		$variable_pricing = edd_get_variable_prices( $download[ 'id' ] );
	
		foreach ( $variable_pricing as $key => $value ) {
			$what = isset( $download[ 'options' ][ 'price_id' ] ) ? $download[ 'options' ][ 'price_id' ] : 0;

			if ( ! isset ( $variable_pricing[ $what ][ 'bought' ] ) ) {
				$variable_pricing[ $what ][ 'bought' ] = 0;
			}

			$current = $variable_pricing[ $what ][ 'bought' ];

			if ( $key === $what ) {
				if ( 'increase' == $direction ) {
					$variable_pricing[ $what ][ 'bought' ] = $current + $download[ 'quantity' ];
				} else {
					$variable_pricing[ $what ][ 'bought' ] = $current - $download[ 'quantity' ];
				}
			}
		}

		update_post_meta( $download[ 'id' ], 'edd_variable_prices', $variable_pricing );
	}
}

/**
 * Log preapproved payment total for campaigns. 
 * 
 * @since 1.8.7
 *
 * @param int $payment the ID number of the payment
 * @param string $new_status
 * @param string $old_status
 * @return void
 */
function atcf_log_preapproval_payment_total( $payment_id, $new_status, $old_status ) {
	global $edd_logs;

	// If we're in test mode, don't proceed unless expected.
	if ( edd_is_test_mode() && ! apply_filters( 'edd_log_test_payment_stats', false ) ) {
		return;
	}

	// Don't do anything if $new_status and $old_status are the same
	if ( $new_status == $old_status ) {
		return;
	}

	// If the status has become preapproved.
	if ( 'preapproval' == $new_status ) {

		atcf_update_preapproval_total( $payment_id, 'increase' );

	}
	// If the status was preapproved.
	elseif ( 'preapproval' == $old_status ) {

		atcf_update_preapproval_total( $payment_id, 'decrease' );

	}	
}
add_action( 'edd_update_payment_status', 'atcf_log_preapproval_payment_total', 100, 3 );

/**
 * Update the preapproval total for all the campaigns donated to in the payment. 
 *
 * @param 	int $payment
 * @param 	string $direction
 * @return  void
 * @since   1.8.7
 */
function atcf_update_preapproval_total( $payment_id, $direction ) {
	$downloads = edd_get_payment_meta_cart_details( $payment_id );

	if ( ! is_array( $downloads ) ) {
		return;
	}

	foreach ( $downloads as $download ) {
		$preapproval_total = get_post_meta( $download[ 'id' ], '_edd_download_preapproved_earnings', true );

		if ( ! $preapproval_total ) {
			$preapproval_total = 0;
		}

		if ( 'increase' == $direction ) {

			$preapproval_total += $download[ 'price' ];

		}
		else {

			$preapproval_total -= $download[ 'price' ];

		}
		
		update_post_meta( $download[ 'id' ], '_edd_download_preapproved_earnings', $preapproval_total );
	}
}

/**
 * Display anonymous backer option on checkout.
 */
function atcf_edd_purchase_form_user_info() {
	if ( ! atcf_theme_supports( 'anonymous-backers' ) )
		return;
?>
	<p id="edd-anon-wrap">
		<label class="edd-label" for="edd-anon">
			<input class="edd-input" type="checkbox" name="edd_anon" id="edd-anon" style="vertical-align: middle;" />
			<?php _e( 'Hide name on backers list?', 'atcf' ); ?>
		</label>
	</p>
<?php
}
add_action( 'edd_purchase_form_after_cc_form', 'atcf_edd_purchase_form_user_info' );

/**
 * Save if the user wants to remain anonymous.
 *
 * This is up to the theme to actually honor.
 *
 * @since Astoundify Crowdfunding 1.2
 *
 * @param arrray $payment_meta Array of payment meta about to be saved
 * @return array $payment_meta An updated array of payment meta
 */
function atcf_anon_save_meta( $payment_meta ) {
	$payment_meta[ 'anonymous' ] = isset ( $_POST[ 'edd_anon' ] ) ? 1 : 0;

	return $payment_meta;
}
add_filter( 'edd_payment_meta', 'atcf_anon_save_meta' );

/**
 * Custom pledge level fix.
 *
 * If there is a custom price, figure out the difference
 * between that, and the price level they have chosen. Store
 * the differene in the cart item meta, so it can be added to
 * the total in the future.
 *
 * @since Astoundify Crowdfunding 1.6
 *
 * @param array $cart_item The current cart item to be added.
 * @return array $cart_item The modified cart item.
 */
function atcf_edd_add_to_cart_item( $cart_item ) {
	if ( isset ( $_POST[ 'post_data' ] ) ) {
		$post_data = array();
		parse_str( $_POST[ 'post_data' ], $post_data );

		$custom_price = $post_data[ 'atcf_custom_price' ];
	} else {
		$custom_price = $_POST[ 'atcf_custom_price' ];
	}

	$custom_price = edd_sanitize_amount( $custom_price );

	$price        = edd_get_cart_item_price( $cart_item[ 'id' ], $cart_item[ 'options' ], edd_prices_include_tax() );

	if ( $custom_price > $price ) {
		$cart_item[ 'options' ][ 'atcf_extra_price' ] = $custom_price - $price;
	}

	return $cart_item;
}
add_filter( 'edd_add_to_cart_item', 'atcf_edd_add_to_cart_item' );
add_filter( 'edd_ajax_pre_cart_item_template', 'atcf_edd_add_to_cart_item' );

/**
 * Calculate the cart item total based on the existence of
 * an additional pledge amount.
 *
 * @since Astoundify Crowdfunding 1.6
 *
 * @param int $price The current price.
 * @param int $item_id The ID of the cart item.
 * @param array $options Item meta for the current cart item.
 * @return int $price The updated price.
 */
function atcf_edd_cart_item_price( $price, $item_id, $options = array() ) {
	if ( isset ( $options[ 'atcf_extra_price' ] ) ) {
		$price = $price + $options[ 'atcf_extra_price' ];
	}

	return $price;
}
add_filter( 'edd_cart_item_price', 'atcf_edd_cart_item_price', 10, 3 );

/**
 * Don't allow multiple pledges to be made at once if
 * it is not set to allow them to. When a single campaign page
 * is loaded (they are browsing again), clear their cart.
 *
 * @since Appthemer CrowdFunding 1.8
 *
 * @return void
 */
function atcf_clear_cart() {
	if ( is_admin() || defined( 'DOING_AJAX' ) )
		return;

	edd_empty_cart();
}

if ( apply_filters( 'atcf_prevent_multi_pledge_cart', false ) ) {
	add_action( 'atcf_found_single', 'atcf_clear_cart' );
}