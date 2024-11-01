<?php

namespace WodenEvents\Includes;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use MrShan0\PHPFirestore\FirestoreClient;
use WodenEvents\Admin\Admin;
use WodenEvents\Admin\Settings;

require plugin_dir_path( __DIR__ )  . 'vendor/autoload.php';


class WodenEvents {

    protected $firestore;

	protected $loader;

	protected $plugin_name;

	protected $version;

	public function __construct() {
		if ( defined( 'WODEN_EVENTS_VERSION' ) ) {
			$this->version = WODEN_EVENTS_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'woden-events';
		$this->load_dependencies();
		$this->define_admin_hooks();
	}

	private function load_dependencies() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . '.env.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/loader.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/admin.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/settings.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/guzzle/middlewarefactory.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/guzzle/refreshtoken.php';

        $this->loader = new Loader();

        $this->firestore = $this->setupFirestore();
	}

	private function define_admin_hooks() {
		$plugin_admin = new Admin( $this->get_plugin_name(), $this->get_version(), $this->firestore );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

        //WooCommerce
		$this->loader->add_action( 'woocommerce_product_options_pricing', $plugin_admin, 'custom_product_field');
		$this->loader->add_action( 'woocommerce_process_product_meta', $plugin_admin, 'save_custom_field_to_products' );
		$this->loader->add_action( 'woocommerce_admin_order_data_after_order_details', $plugin_admin, 'show_attendees_sent_to_firebase' );

		$this->loader->add_action( 'woocommerce_checkout_create_order', $plugin_admin, 'check_unique_email_addresses', 10, 1 );

        // We process payments using different hooks. Duplicates are discarted
        //$this->loader->add_action( 'woocommerce_payment_complete', $plugin_admin, 'payment_complete' );
        $this->loader->add_action( 'woocommerce_order_status_processing', $plugin_admin, 'payment_complete', 10, 1 );
		$this->loader->add_action( 'woocommerce_order_status_completed', $plugin_admin, 'payment_complete', 10, 1 );
		//agregar otros hooks.

		//Settings
		$plugin_admin_settings = new Settings( $this->get_plugin_name(), $this->get_version(), $this->firestore );

        $this->loader->add_action( 'admin_menu', $plugin_admin_settings, 'setting_page' );
        $this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin_settings, 'enqueue_styles' );

        $this->loader->add_filter( 'plugin_action_links_WodenEvents/WodenEvents.php', $plugin_admin_settings, 'action_links', 10, 2 );

    }

	private function setupFirestore()
    {
        $middlewareFactory = new Guzzle\MiddlewareFactory();

        $handler = new CurlHandler();

        $stack = HandlerStack::create( $handler );
        $stack->push( $middlewareFactory->retry() );

        $config = [
            'query' => [
                'key' => WODEN_EVENTS_WEB_API_KEY
            ],
            'handler' => $stack
        ];

        $guzzle = new GuzzleClient( $config );

        $stack->push( new Guzzle\RefreshToken($guzzle) );

        $firestore = new FirestoreClient(WODEN_EVENTS_PROJECT_ID, WODEN_EVENTS_WEB_API_KEY, [
            'database' => '(default)',
        ], $guzzle);

        $firestore->authenticator()->setCustomToken( get_option('wodenevents_firestore_id') );

        $stack->push(new Guzzle\RefreshToken($guzzle));

        return $firestore;
    }

	public function run() {
		$this->loader->run();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_loader() {
		return $this->loader;
	}

	public function get_version() {
		return $this->version;
	}

	public static function log( $message, $argument = [], $log_type = '' ) {
		if ( true !== WP_DEBUG ) {
			return;
		}

		if ( is_object( $message ) || is_array( $message ) ) {
			$message = print_r($message, true);
		} else {
			$message = str_replace( array( "\r", "\n" ), '', $message );
		}

		if ( is_array( $argument ) ) {
			foreach ( $argument AS $i => $value ) {
				if ( isset( $argument[ $i ] ) && ! empty( $argument[ $i ] ) && is_scalar( $argument[ $i ] ) ) {
					$message .= ", arg$i: " . $argument[ $i ];
				}
			}
		}

		if ( ! empty( $log_type ) ) {
			$log_type = str_replace(' ', '', $log_type);
			$log_type = substr( $log_type, 0, 10 );
			$log_type = '-' . $log_type;
		}

		error_log( "#WodenEvents{$log_type}: $message" );
	}

}