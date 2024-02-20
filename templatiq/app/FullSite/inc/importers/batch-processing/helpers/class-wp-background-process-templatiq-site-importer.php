<?php
/**
 * Single Page Background Process
 *
 * @package Templatiq Sites
 * @since 2.0.0
 */

if ( class_exists( 'WP_Background_Process' ) ) :

	/**
	 * Image Background Process
	 *
	 * @since 2.0.0
	 */
	class WP_Background_Process_Templatiq_Site_Importer extends WP_Background_Process {

		/**
		 * Image Process
		 *
		 * @var string
		 */
		protected $action = 'templatiq_site_importer';

		/**
		 * Task
		 *
		 * Override this method to perform any actions required on each
		 * queue item. Return the modified item for further processing
		 * in the next pass through. Or, return false to remove the
		 * item from the queue.
		 *
		 * @since 2.0.0
		 *
		 * @param object $object Queue item object.
		 * @return mixed
		 */
		protected function task( $object ) {

			$process = $object['instance'];
			$method  = $object['method'];
			
			if ( 'import_all_categories_and_tags' === $method ) {
				templatiq_sites_error_log( '-------- Importing All Categories and Tags --------' );
				update_site_option( 'templatiq-sites-batch-status-string', 'Importing All Categories and Tags' );
				$process->import_all_categories_and_tags();
			} elseif ( 'import_all_categories' === $method ) {
				templatiq_sites_error_log( '-------- Importing All Categories --------' );
				update_site_option( 'templatiq-sites-batch-status-string', 'Importing All Categories' );
				$process->import_all_categories();
			} elseif ( 'import_sites' === $method ) {
				templatiq_sites_error_log( '-------- Importing Sites --------' );
				$page = $object['page'];
				templatiq_sites_error_log( 'Inside Batch ' . $page );
				update_site_option( 'templatiq-sites-batch-status-string', 'Inside Batch ' . $page );
				$process->import_sites( $page );
			} elseif ( 'import_blocks' === $method ) {
				templatiq_sites_error_log( '-------- Importing Blocks --------' );
				$page = $object['page'];
				templatiq_sites_error_log( 'Inside Batch ' . $page );
				update_site_option( 'templatiq-sites-batch-status-string', 'Inside Batch ' . $page );
				$process->import_blocks( $page );
			} elseif ( 'import_block_categories' === $method ) {
				templatiq_sites_error_log( '-------- Importing Blocks Categories --------' );
				update_site_option( 'templatiq-sites-batch-status-string', 'Importing Blocks Categories' );
				$process->import_block_categories();
			}

			return false;
		}

		/**
		 * Complete
		 *
		 * Override if applicable, but ensure that the below actions are
		 * performed, or, call parent::complete().
		 *
		 * @since 2.0.0
		 */
		protected function complete() {
			parent::complete();

			templatiq_sites_error_log( esc_html__( 'All processes are complete', 'templatiq' ) );
			update_site_option( 'templatiq-sites-batch-status-string', 'All processes are complete' );
			delete_site_option( 'templatiq-sites-batch-status' );
			update_site_option( 'templatiq-sites-batch-is-complete', 'yes' );

			do_action( 'templatiq_sites_site_import_batch_complete' );
		}

	}

endif;
