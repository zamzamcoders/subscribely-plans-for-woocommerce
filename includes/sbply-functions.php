<?php
/**
 * Global helper functions.
 *
 * @package Sbply
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load a subscription.
 *
 * @param int $subscription_id Subscription ID.
 * @return Sbply_Subscription|false False when it does not exist.
 */
function sbply_get_subscription( $subscription_id ) {
	$subscription_id = absint( $subscription_id );

	if ( ! $subscription_id ) {
		return false;
	}

	try {
		return new Sbply_Subscription( $subscription_id );
	} catch ( Exception $e ) {
		return false;
	}
}

/**
 * Query subscriptions.
 *
 * @param array $args See Sbply_Subscription_Data_Store::query().
 * @return Sbply_Subscription[]
 */
function sbply_get_subscriptions( $args = array() ) {
	$store = WC_Data_Store::load( 'sbply-subscription' );
	$ids   = $store->query( $args );

	return array_values( array_filter( array_map( 'sbply_get_subscription', $ids ) ) );
}

/**
 * Subscription statuses and their labels.
 *
 * @return array<string,string>
 */
function sbply_get_subscription_statuses() {
	$statuses = array(
		'pending'   => _x( 'Pending', 'subscription status', 'subscribely-plans-for-woocommerce' ),
		'active'    => _x( 'Active', 'subscription status', 'subscribely-plans-for-woocommerce' ),
		'on-hold'   => _x( 'On hold', 'subscription status', 'subscribely-plans-for-woocommerce' ),
		'cancelled' => _x( 'Cancelled', 'subscription status', 'subscribely-plans-for-woocommerce' ),
		'expired'   => _x( 'Expired', 'subscription status', 'subscribely-plans-for-woocommerce' ),
	);

	/**
	 * Filter the registered subscription statuses.
	 *
	 * @param array<string,string> $statuses Status slug => label.
	 */
	return apply_filters( 'sbply/subscription_statuses', $statuses );
}

/**
 * Human label for a subscription status.
 *
 * @param string $status Status slug.
 * @return string
 */
function sbply_get_status_label( $status ) {
	$statuses = sbply_get_subscription_statuses();
	return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
}

/**
 * Billing periods a plan can use.
 *
 * @return array<string,string> Period slug => label.
 */
function sbply_get_billing_periods() {
	return array(
		'day'   => __( 'Day', 'subscribely-plans-for-woocommerce' ),
		'week'  => __( 'Week', 'subscribely-plans-for-woocommerce' ),
		'month' => __( 'Month', 'subscribely-plans-for-woocommerce' ),
		'year'  => __( 'Year', 'subscribely-plans-for-woocommerce' ),
	);
}

/**
 * "3 months", "1 week" … with the count included.
 *
 * @param string $period Billing period slug.
 * @param int    $count  Number of periods.
 * @return string
 */
function sbply_get_period_count_label( $period, $count ) {
	$count = (int) $count;
	$num   = number_format_i18n( $count );

	switch ( $period ) {
		case 'day':
			/* translators: %s: number of days. */
			return sprintf( _n( '%s day', '%s days', $count, 'subscribely-plans-for-woocommerce' ), $num );
		case 'week':
			/* translators: %s: number of weeks. */
			return sprintf( _n( '%s week', '%s weeks', $count, 'subscribely-plans-for-woocommerce' ), $num );
		case 'year':
			/* translators: %s: number of years. */
			return sprintf( _n( '%s year', '%s years', $count, 'subscribely-plans-for-woocommerce' ), $num );
		case 'month':
		default:
			/* translators: %s: number of months. */
			return sprintf( _n( '%s month', '%s months', $count, 'subscribely-plans-for-woocommerce' ), $num );
	}
}

/**
 * Billing frequency text: "/ month", "every 2 weeks".
 *
 * @param int    $interval Bill every N periods.
 * @param string $period   Billing period slug.
 * @return string
 */
function sbply_get_billing_frequency_label( $interval, $period ) {
	$interval = max( 1, (int) $interval );

	if ( 1 === $interval ) {
		$singular = array(
			'day'   => __( 'day', 'subscribely-plans-for-woocommerce' ),
			'week'  => __( 'week', 'subscribely-plans-for-woocommerce' ),
			'month' => __( 'month', 'subscribely-plans-for-woocommerce' ),
			'year'  => __( 'year', 'subscribely-plans-for-woocommerce' ),
		);
		$name     = isset( $singular[ $period ] ) ? $singular[ $period ] : $singular['month'];

		/* translators: %s: billing period name, e.g. "month". */
		return sprintf( __( '/ %s', 'subscribely-plans-for-woocommerce' ), $name );
	}

	/* translators: %s: billing interval with period, e.g. "2 weeks". */
	return sprintf( __( 'every %s', 'subscribely-plans-for-woocommerce' ), sbply_get_period_count_label( $period, $interval ) );
}

/**
 * Full recurring price text: "$18.00 / month for 12 months".
 *
 * @param string $price_html Formatted price (already passed through wc_price()).
 * @param int    $interval   Bill every N periods.
 * @param string $period     Billing period slug.
 * @param int    $length     Number of billing cycles; 0 means until cancelled.
 * @return string HTML.
 */
function sbply_format_recurring_price( $price_html, $interval, $period, $length = 0 ) {
	$text = $price_html . ' <span class="sbply-frequency">' . esc_html( sbply_get_billing_frequency_label( $interval, $period ) ) . '</span>';

	$length = (int) $length;
	if ( $length > 0 ) {
		$total = $length * max( 1, (int) $interval );
		/* translators: %s: total subscription duration, e.g. "12 months". */
		$text .= ' <span class="sbply-length">' . esc_html( sprintf( __( 'for %s', 'subscribely-plans-for-woocommerce' ), sbply_get_period_count_label( $period, $total ) ) ) . '</span>';
	}

	return $text;
}

/**
 * Add N billing periods to a timestamp, in UTC.
 *
 * Months and years clamp to the last day of the target month, so 31 January
 * plus one month is 28/29 February rather than 2/3 March. Callers that step
 * through several cycles should always add from the original anchor
 * (anchor + 3 months), never chain single steps, or a clamped date would
 * drift the rest of the schedule.
 *
 * @param int    $timestamp Unix timestamp to start from.
 * @param int    $count     Number of periods to add.
 * @param string $period    Billing period slug.
 * @return int Unix timestamp.
 */
function sbply_add_periods( $timestamp, $count, $period ) {
	$count = (int) $count;
	$utc   = new DateTimeZone( 'UTC' );
	$date  = ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( $utc );

	switch ( $period ) {
		case 'day':
			return $date->modify( sprintf( '+%d days', $count ) )->getTimestamp();
		case 'week':
			return $date->modify( sprintf( '+%d weeks', $count ) )->getTimestamp();
		case 'year':
			$months = $count * 12;
			break;
		case 'month':
		default:
			$months = $count;
	}

	$year  = (int) $date->format( 'Y' );
	$month = (int) $date->format( 'n' ) - 1 + $months; // Zero-based for the arithmetic.
	$day   = (int) $date->format( 'j' );

	$year += (int) floor( $month / 12 );
	$month = ( ( $month % 12 ) + 12 ) % 12 + 1;

	$days_in_month = (int) ( new DateTimeImmutable( sprintf( '%04d-%02d-01', $year, $month ), $utc ) )->format( 't' );

	return $date->setDate( $year, $month, min( $day, $days_in_month ) )->getTimestamp();
}
