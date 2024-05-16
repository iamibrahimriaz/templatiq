<?php
/**
 * @author  wpWax
 * @since   1.0.0
 * @version 1.0.0
 */

namespace Templatiq\Integrations\Directorist;

use Templatiq\Repositories\DependencyRepository;
use Templatiq\Utils\Http;
use Templatiq\Utils\Response;

/**
 * Will unlock the directory related templates
 * When directorist is active
 * & has a valid token
 * & will only unlock the themes design that already purchased
 */
class Repository {
	private string $cloud_endpoint;

	public function __construct() {
		$this->cloud_endpoint = TEMPLATIQ_CLOUD_BASE;
	}

	public function is_active() {
		return ( new DependencyRepository )->is_active( 'directorist/directorist-base.php' );
	}

	public function token(): string {
		return 'directorist token from user meta';
	}

	public function sync_membership_with_cloud() {
		$http     = new Http( $this->cloud_endpoint . '/account/directorist-sync' );
		$response = $http->body(
			[
				'token' => $this->token(),
			] )
			->post()
		// ->log()
			->response();

		if ( is_wp_error( $response ) ) {
			return Response::error( 'invalid_data', $response->get_error_message(), 'sync_cloud', 404 );
		}

		if ( isset( $response['status'] ) && 'error' === $response['status'] ) {
			return Response::error( 'invalid_data', $response['message'], 'get_content', 404 );
		}

		if ( ! empty( $response['body'] ) && is_string( $response['body'] ) ) {
			$data = json_decode( $response['body'], true );
		} else {
			$data = isset( $response['body'] ) ? (array) $response['body'] : [];
		}

		return $data;
	}

	public function get_directory_types(): array {
		$listing_types = get_terms( [
			'taxonomy'   => 'atbdp_listing_types',
			'hide_empty' => false,
			'orderby'    => 'date',
			'order'      => 'DESC',
		] );

		foreach ( $listing_types as $key => $type ) {
			$listing_types[$key]->meta = $this->get_directory_type_meta( $type->term_id );
		}

		return $listing_types;
	}

	private function get_directory_type_meta( $id ) {
		$meta_val_with_array = get_term_meta( $id, '', true );
		$meta                = [];
		foreach ( $meta_val_with_array as $key => $value ) {
			$meta[$key] = $value[0];
		}

		return $meta;
	}

	public function erase_existing_data() {
		// Delete all existing data.
		// Warning: You can't revert this.
		global $wpdb;

		include_once PLUGINDIR . DIRECTORY_SEPARATOR . 'directorist' . DIRECTORY_SEPARATOR . "directorist-base.php";

		// Delete selected pages.
		wp_delete_post( get_directorist_option( 'add_listing_page' ), true );
		wp_delete_post( get_directorist_option( 'all_listing_page' ), true );
		wp_delete_post( get_directorist_option( 'user_dashboard' ), true );
		wp_delete_post( get_directorist_option( 'author_profile_page' ), true );
		wp_delete_post( get_directorist_option( 'all_categories_page' ), true );
		wp_delete_post( get_directorist_option( 'single_category_page' ), true );
		wp_delete_post( get_directorist_option( 'all_locations_page' ), true );
		wp_delete_post( get_directorist_option( 'single_location_page' ), true );
		wp_delete_post( get_directorist_option( 'single_tag_page' ), true );
		wp_delete_post( get_directorist_option( 'custom_registration' ), true );
		wp_delete_post( get_directorist_option( 'user_login' ), true );
		wp_delete_post( get_directorist_option( 'search_listing' ), true );
		wp_delete_post( get_directorist_option( 'search_result_page' ), true );
		wp_delete_post( get_directorist_option( 'checkout_page' ), true );
		wp_delete_post( get_directorist_option( 'payment_receipt_page' ), true );
		wp_delete_post( get_directorist_option( 'transaction_failure_page' ), true );
		wp_delete_post( get_directorist_option( 'privacy_policy' ), true );
		wp_delete_post( get_directorist_option( 'terms_conditions' ), true );

		// Delete posts + data.
		$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type IN ( 'at_biz_dir', 'atbdp_fields', 'atbdp_orders', 'atbdp_listing_review' );" );

		//Delete all postmeta
		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id Not IN  (SELECT id FROM {$wpdb->posts})" );

		//Delete term relationships
		$wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE object_id Not IN  (SELECT id FROM {$wpdb->posts})" );

		//Delete all taxonomy
		$wpdb->query( "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'at_biz_dir-location'" );
		$wpdb->query( "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'at_biz_dir-category'" );
		$wpdb->query( "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'at_biz_dir-tags'" );
		//Delete all term meta
		$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE term_id Not IN  (SELECT term_id FROM {$wpdb->term_taxonomy})" );
		$wpdb->query( "DELETE FROM {$wpdb->terms} WHERE term_id Not IN  (SELECT term_id FROM {$wpdb->term_taxonomy})" );
		//Delete review database
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}atbdp_review" );
		//Delete usermeta.
		$wpdb->query( "DELETE FROM $wpdb->usermeta WHERE meta_key LIKE '%atbdp%';" );
		$wpdb->query( "DELETE FROM $wpdb->usermeta WHERE meta_key = 'pro_pic';" );

		// Delete all the Plugin Options
		$atbdp_settings = [
			"{$wpdb->prefix}atbdp_review_db_version",
			'atbdp_option',
			'widget_bdpl_widget',
			'widget_bdvd_widget',
			'widget_bdco_widget',
			'widget_bdsb_widget',
			'widget_bdlf_widget',
			'widget_bdcw_widget',
			'widget_bdlw_widget',
			'widget_bdtw_widget',
			'widget_bdsw_widget',
			'widget_bdmw_widget',
			'widget_bdamw_widget',
			'widget_bdsl_widget',
			'widget_bdsi_widget',
			'widget_bdfl_widget',
			'atbdp_meta_version',
			'atbdp_pages_version',
			'atbdp_roles_mapped',
			'atbdp_roles_version',
			'at_biz_dir-location_children',
			'at_biz_dir-category_children',
		];
		foreach ( $atbdp_settings as $settings ) {
			delete_option( $settings );
		}
	}
}