<?php
/**
 * Image Importer
 *
 * @see https://github.com/elementor/elementor/blob/master/includes/template-library/classes/class-import-images.php
 *
 * => How to use?
 *
 *  $image = array(
 *      'url' => '<image-url>',
 *      'id'  => '<image-id>',
 *  );
 *
 *  $downloaded_image = Templatiq_Sites_Image_Importer::get_instance()->import( $image );
 *
 * @package Templatiq Sites
 * @since 1.0.14
 */

if ( ! class_exists( 'Templatiq_Sites_Image_Importer' ) ):

	/**
	 * Templatiq Sites Image Importer
	 *
	 * @since 1.0.14
	 */
	class Templatiq_Sites_Image_Importer {

		/**
		 * Instance
		 *
		 * @since 1.0.14
		 * @var object Class object.
		 * @access private
		 */
		private static $instance;

		/**
		 * Images IDs
		 *
		 * @var array   The Array of already image IDs.
		 * @since 1.0.14
		 */
		private $already_imported_ids = [];

		/**
		 * Initiator
		 *
		 * @since 1.0.14
		 * @return object initialized object of class.
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor
		 *
		 * @since 1.0.14
		 */
		public function __construct() {

			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			WP_Filesystem();
		}

		/**
		 * Process Image Download
		 *
		 * @since 1.0.14
		 * @param  array $attachments Attachment array.
		 * @return array              Attachment array.
		 */
		public function process( $attachments ) {

			$downloaded_images = [];

			foreach ( $attachments as $key => $attachment ) {
				$downloaded_images[] = $this->import( $attachment );
			}

			return $downloaded_images;
		}

		/**
		 * Get Hash Image.
		 *
		 * @since 1.0.14
		 * @param  string $attachment_url Attachment URL.
		 * @return string                 Hash string.
		 */
		public function get_hash_image( $attachment_url ) {
			return sha1( $attachment_url );
		}

		/**
		 * Get Saved Image.
		 *
		 * @since 1.0.14
		 * @param  string $attachment   Attachment Data.
		 * @return string                 Hash string.
		 */
		private function get_saved_image( $attachment ) {

			if ( apply_filters( 'templatiq_sites_image_importer_skip_image', false, $attachment ) ) {
				Templatiq_Sites_Importer_Log::add( 'BATCH - SKIP Image - {from filter} - ' . $attachment['url'] . ' - Filter name `templatiq_sites_image_importer_skip_image`.' );

				return [
					'status'     => true,
					'attachment' => $attachment,
				];
			}

			global $wpdb;

			// 1. Is already imported in Batch Import Process?
			$post_id = $wpdb->get_var(  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- We are checking if this image is already processed. WO_Query would have been overkill.
				$wpdb->prepare(
					'SELECT `post_id` FROM `' . $wpdb->postmeta . '`
							WHERE `meta_key` = \'_templatiq_sites_image_hash\'
								AND `meta_value` = %s
						;',
					$this->get_hash_image( $attachment['url'] )
				)
			);

			// 2. Is image already imported though XML?
			if ( empty( $post_id ) ) {

				// Get file name without extension.
				// To check it exist in attachment.
				$filename = basename( $attachment['url'] );

				// Find the attachment by meta value.
				// Code reused from Elementor plugin.
				$post_id = $wpdb->get_var(  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- We are checking if this attachment is already processed. WO_Query would have been overkill.
					$wpdb->prepare(
						"SELECT post_id FROM {$wpdb->postmeta}
							WHERE meta_key = '_wp_attached_file'
							AND meta_value LIKE %s",
						'%/' . $filename . '%'
					)
				);

				Templatiq_Sites_Importer_Log::add( 'BATCH - SKIP Image {already imported from xml} - ' . $attachment['url'] );
			}

			if ( $post_id ) {
				$new_attachment = [
					'id'  => $post_id,
					'url' => wp_get_attachment_url( $post_id ),
				];
				$this->already_imported_ids[] = $post_id;

				return [
					'status'     => true,
					'attachment' => $new_attachment,
				];
			}

			return [
				'status'     => false,
				'attachment' => $attachment,
			];
		}

		/**
		 * Import Image
		 *
		 * @since 1.0.14
		 * @param  array $attachment Attachment array.
		 * @return array              Attachment array.
		 */
		public function import( $attachment ) {

			if ( isset( $attachment['url'] ) && ! templatiq_sites_is_valid_url( $attachment['url'] ) ) {
				return $attachment;
			}

			error_log( 'I have got a request to import this file' . $attachment['url'] );

			Templatiq_Sites_Importer_Log::add( 'Source - ' . $attachment['url'] );
			$saved_image = $this->get_saved_image( $attachment );
			Templatiq_Sites_Importer_Log::add( 'Log - ' . wp_json_encode( $saved_image['attachment'] ) );

			if ( $saved_image['status'] ) {
				error_log( 'saved_image' . print_r(  $saved_image, true) );
				return $saved_image['attachment'];
			}

			$file_content = wp_remote_retrieve_body(
				wp_safe_remote_get(
					$attachment['url'],
					[
						'timeout'   => '60',
						'sslverify' => false,
					]
				)
			);

			// Empty file content?
			if ( empty( $file_content ) ) {

				Templatiq_Sites_Importer_Log::add( 'BATCH - FAIL Image {Error: Failed wp_remote_retrieve_body} - ' . $attachment['url'] );

				return $attachment;
			}

			// Extract the file name and extension from the URL.
			$filename = basename( $attachment['url'] );

			$upload = wp_upload_bits( $filename, null, $file_content );

			templatiq_sites_error_log( $filename );
			templatiq_sites_error_log( wp_json_encode( $upload ) );

			$post = [
				'post_title' => $filename,
				'guid'       => $upload['url'],
			];
			templatiq_sites_error_log( wp_json_encode( $post ) );

			$info = wp_check_filetype( $upload['file'] );
			if ( $info ) {
				$post['post_mime_type'] = $info['type'];
			} else {
				// For now just return the origin attachment.
				return $attachment;
			}

			$post_id = wp_insert_attachment( $post, $upload['file'] );
			wp_update_attachment_metadata(
				$post_id,
				wp_generate_attachment_metadata( $post_id, $upload['file'] )
			);
			update_post_meta( $post_id, '_templatiq_sites_image_hash', $this->get_hash_image( $attachment['url'] ) );

			Templatiq_WXR_Importer::instance()->track_post( $post_id );

			$new_attachment = [
				'id'  => $post_id,
				'url' => $upload['url'],
			];

			Templatiq_Sites_Importer_Log::add( 'BATCH - SUCCESS Image {Imported} - ' . $new_attachment['url'] );

			$this->already_imported_ids[] = $post_id;

			// error_log( 'new_attachment' . print_r( $new_attachment, true ) );

			return $new_attachment;
		}

		/**
		 * Is Image URL
		 *
		 * @since 1.3.10
		 *
		 * @param  string $url URL.
		 * @return boolean
		 */
		public function is_image_url( $url = '' ) {
			if ( empty( $url ) ) {
				return false;
			}

			if ( templatiq_sites_is_valid_image( $url ) ) {
				return true;
			}

			return false;
		}

	}

	/**
	 * Kicking this off by calling 'get_instance()' method
	 */
	Templatiq_Sites_Image_Importer::get_instance();

endif;