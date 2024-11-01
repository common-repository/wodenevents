<?php

namespace WodenEvents\Admin;

use MrShan0\PHPFirestore\Fields\FirestoreObject;
use MrShan0\PHPFirestore\Fields\FirestoreTimestamp;
use MrShan0\PHPFirestore\FirestoreDocument;
use WodenEvents\Includes\WodenEvents;

class Admin {

	private $plugin_name;

	private $version;

	private $firestore;

	public function __construct( $plugin_name, $version, $firestore ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;

        $this->firestore = $firestore;
	}

	public function custom_product_field() {
		$product = wc_get_product();

	    $event_ids = self::get_associated_events();

		/**
		 * https://stackoverflow.com/questions/45212221/add-custom-fields-to-woocomerce-product-setting-pages-in-the-shipping-tab
		 */

		echo '</div><div class="options_group">'; // New option group

		?><p class="form-field dimensions_field">
        <label for="product_shipping_class"><?php echo __('Event from WODEN', 'wodenevents'); ?></label>

        <select name="wodenevents_event_ticket">
			<?php


            if (is_array($product->get_category_ids()) AND in_array(get_option( 'wodenevents_category_id' ), $product->get_category_ids())) {
	            try {
                    $mask = 'mask.fieldPaths=companyId';
                    $company = $this->firestore->getDocument('users/' . get_option('wodenevents_firestore_user_id'),
                        ['query' => $mask]
                    );

                    $wodenevents_events = $this->runQuery('events', [ 'name', 'eventId', 'startDate' ], [ 'companyId', 'EQUAL', $company->get('companyId') ], [ 'startDate', 'DESCENDING' ]);
	            } catch ( \Exception $e ) {
		            echo '<option value="" disabled="disabled" selected="selected">' . __('Error connecting to WODEN', 'wodenevents') . '</option>';
                    echo '</select></p>';
		            WodenEvents::log( $e->getMessage(), null, 'Firebase' );

		            return;
                }

                if ( is_array($wodenevents_events) && !empty($wodenevents_events) ) {
	                //Sacamos los eventId de la consulta anterior para armar un array anidado con los tickets de cada evento.
                    $wodenevents_event_ids = [];
                    foreach ( $wodenevents_events AS $event ) {
                        $wodenevents_event_ids[] = $event->get('eventId');
                    }

                    //Consultamos los tickets con eventId que sacamos en el anterior foreach
                    $tickets_combined = [];
                    if ( ( get_option( 'wodenevents_cachetime_tickets' ) + 60*60 ) < current_time( 'timestamp' ) ) {
                        foreach( $wodenevents_event_ids AS $woden_event_id ) {
                            $where = [ 'eventId', 'EQUAL', $woden_event_id ];
                            $wodenevents_tickets = $this->runQuery('tickets', [ 'name', 'price', 'eventId', 'ticketId' ], $where);

                            $tickets_combined = array_merge($tickets_combined, $wodenevents_tickets);

                            update_option( 'wodenevents_cahce_tickets', serialize($tickets_combined) );
                            update_option( 'wodenevents_cachetime_tickets', current_time( 'timestamp' ) );
                        }
                    } else {
                        $tickets_combined = unserialize( get_option( 'wodenevents_cahce_tickets' ) );
                    }

                    if ( ! empty( $tickets_combined ) ) {
                        echo '<option value="">' . __('Not associated', 'wodenevents') . '</option>';

                        foreach ( $wodenevents_events as $event ) {
                            $woden_display = '';

                            foreach( $tickets_combined AS $ticket ) {
                                try {
                                    if ( $event->get('eventId') != $ticket->get('eventId') ) {
                                        continue;
                                    }
                                } catch (\Exception $e) {
                                    continue;
                                }

                                $select_extras = selected( get_post_meta($product->get_id(), '_wodenevents_ticket_id', true), $ticket->get('ticketId'), false );

                                if ($product->get_meta('_wodenevents_ticket_id') != $ticket->get('ticketId'))
                                    $select_extras .= ' ' . disabled( array_key_exists($ticket->get('ticketId'), $event_ids), true, false);

                                $woden_display .= '<option value="' . esc_attr( $event->get('eventId') . ' ' . $ticket->get('ticketId')) . '"' . $select_extras . '>-- ' . esc_html( $ticket->get('name') ) . '</option>';
                            }

                            if ( empty( $woden_display ) ) {
                                continue;
                            }

                            $eventTimestamp = strtotime($event->get('startDate')->parseValue());
                            $startDate = date('Y-m-d', $eventTimestamp);
                            echo '<option disabled="disabled">' . esc_html( $event->get('name') . ' - ' . $startDate ) . '</option>';

                            echo $woden_display;
                        }
                    }
                } else {
                    echo '<option value="" disabled="disabled" selected="selected">' . __('There are no events belonging to this company', 'wodenevents') . '</option>';
                }
            } else {
	            echo '<option value="" disabled="disabled" selected="selected">' . __('This product doesn\'n belong to the category: ', 'wodenevents') . get_option( 'wodenevents_category_id' ) . '</option>';
            }

			?>
		</select>

		<?php echo wc_help_tip( __('Choose a ticket from the list to associate it with this product.', 'wodenevents' )); ?>
		</p><?php

        woocommerce_wp_checkbox(
            array(
                'id'            => 'wodenevents_unique_email',
                'value'         => ( get_post_meta( $product->get_id(), '_wodenevents_unique_email', true ) === 'yes' ? 'yes' : 'no' ),
                'wrapper_class' => '',
                'label'         => __( 'Unique email?', 'wodenevents' ),
                'description'   => __( 'Prevent duplicated emails for this event in WODEN registrants', 'wodenevents' ),
            )
        );
    }

	public function show_attendees_sent_to_firebase( $order ) {
	    $attendees = '';

		$firestore_ids = $order->get_meta( '_wodenevents_firestore_ids' );

		if ( empty( $firestore_ids ) || ! is_array( $firestore_ids ) ) {
		    return;
        }

	    foreach( $firestore_ids AS $firestore_id => $attendee ) {
	        $attendees .= "$firestore_id => $attendee";
        }

        if ( empty( $attendees ) ) {
	        return;
        }

        $registrants_sent = __('Registrants sent to WodenEvents:', 'wodenevents');

	    echo <<<EOF
<p class="form-field form-field-wide wc-wodenevents-attendees">
    <label for="wodenevents_attendees">$registrants_sent</label>
    <textarea rows="4" disabled="disabled" id="wodenevents_attendees">$attendees</textarea>
</p>
EOF;
    }

	public function save_custom_field_to_products( $post_id ) {
		$product = wc_get_product( $post_id );

        // Update the Unique Email option
        $unique_email = sanitize_text_field( $_POST['wodenevents_unique_email'] ) == 'yes' ? 'yes' : 'no';
        update_post_meta( $post_id, '_wodenevents_unique_email', esc_attr( $unique_email ) );


        if (!(is_array($product->get_category_ids()) AND in_array(get_option( 'wodenevents_category_id' ), $product->get_category_ids()))) {
		    return false;
        }

        //Si no hay un espacio en la variable que entra, esta en el formato incorrecto.
        $event_ticket = sanitize_text_field( $_POST['wodenevents_event_ticket'] );

        //If the submitted value is empty, remove it from the database
        if ( empty( $event_ticket ) ) {
            delete_post_meta( $post_id, '_wodenevents_ticket_id' );
        }

        if ( strpos( $event_ticket, ' ' ) === false ) {
            WodenEvents::log( 'La variable "event_ticket" no esta formateada correctamente' );
            return false;
        }

		$event_ticket = explode( ' ', $event_ticket );

		$event_id = $event_ticket[0];
		$ticket_id = $event_ticket[1];

		if ( ! empty( $ticket_id ) AND  self::get_product_by_associated_ticket_id( $ticket_id ) )
		    return false;

		if( isset( $ticket_id ) )
			update_post_meta( $post_id, '_wodenevents_ticket_id', esc_attr( $ticket_id ) );

		// Decidí llamar esta metakey event2_id debido a que originalmente había una llamada event_id
        if( isset( $event_id ) )
            update_post_meta( $post_id, '_wodenevents_event2_id', esc_attr( $event_id ) );
	}

	public function payment_complete( $order_id ) {
	    $order = wc_get_order( $order_id );

        $firestore_ids = $order->get_meta( '_wodenevents_firestore_ids' );

        if ( is_array( $firestore_ids ) && ! empty( $firestore_ids ) ) {
            WodenEvents::log( 'Ya habíamos procesado la orden #' . $order_id );
            return;
        }

	    $order_email = $order->get_billing_email();
	    $order_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
	    $order_phone = $order->get_billing_phone();
	    $order_currency = $order->get_currency();
        $order_payment_method = $order->get_payment_method();
        $order_payment_method_title = $order->get_payment_method_title();


	    $items = $order->get_items();

	    $attendees = array();

	    foreach ( $items AS $item ) {
            $product_id = $item->get_product_id();

            //Si el sitio tiene WPML activo, es posible que hayan cambiado el ID del producto, consultar el original pues alli es donde se almacena nuestro metadata
            if ( function_exists('icl_object_id') ) {
                $product_id = self::lang_object_ids( $product_id, 'product' );
            }

            $product = wc_get_product( $product_id );

            $product_category_ids = $product->get_category_ids();

            //Si el sitio tiene WPML activo, es posible que hayan cambiado el ID de la categoría, debemos consultar la original
            if ( function_exists('icl_object_id') ) {
                $product_category_ids = self::lang_object_ids( $product_category_ids, 'category' );
            }

            //si no está en una categoría de las configuradas, continuamos
            if ( is_array( $product_category_ids ) AND !in_array(get_option( 'wodenevents_category_id' ), $product_category_ids ) ) {
                WodenEvents::log( 'El producto no pertenece a una categoría configurada para WodenEvents - skipping' );

                continue;
            }

            $ticket_id = $product->get_meta('_wodenevents_ticket_id');
            $event_id = $product->get_meta('_wodenevents_event2_id');

	        /**
	         * Por ahora no enviamos nada a Firestore si no tenemos el ticket_id
	         */
            if (empty($ticket_id)) {
                WodenEvents::log( 'El producto no tiene asociado un ticket con WodenEvents - skipping' );

                continue;
            }

            if (empty($event_id)) {
                WodenEvents::log( 'El producto no tiene asociado un evento con WodenEvents - NOT skipping but investigate' );

                continue;
            }

            $quantity = $item->get_quantity();
            $total = $item->get_total();
            $meta_data = $item->get_meta_data();
            
            $price_per_item = $total / $quantity;


            //Armamos el array para registrants
            $data = [
                'fullName' => $order_name,
                'email' => $order_email,
                'phone' => $order_phone,
                'currency' => $order_currency,
                'price' => $price_per_item,
                'paymentMethod' => $order_payment_method,
                'paymentMethodTitle' => $order_payment_method_title,
            ];

            $attendee = array();
            foreach ($meta_data AS $the_meta_data)
            {
                if ($the_meta_data->key == '_mnhotel_data' AND is_array($the_meta_data->value))
                {
                    foreach ($the_meta_data->value AS $key => $val)
                    {
                        preg_match( '/(\w+)(\d+)/', $key, $matches );

                        //Armamos el array igual a como debe viajar a Firestore
                        if ($matches[1] == 'attendeesname') {
                            $data['fullName'] = empty($val['value']) ? ( $matches[2] + 1 ) . ' ' . $order_name : $val['value'];

                            $attendee[] = $this->_build_registrant_array( $event_id, $ticket_id, $data );
                        } else if ($matches[1] == 'attendeesemail') {
                            /* no asignamos el email porque con _build_registrant_array no sabemos el index */
	                        //$attendee[ $matches[2] ]['email'] = empty($val['value']) ? $order_email : $val['value'];
                        }
                    }

                    //nos salimos del foreach pues ya encontramos el array de attendees.
                    break;
                }
            }

            //No tenemos info de attendees en la metadata de la orden, armémosla.
            if (empty($attendee))
            {
                for ($i = 0; $i < $quantity; $i++)
                {
                    $data['fullName'] = ($i + 1) . ' ' . $order_name;

                    $attendee[] = $this->_build_registrant_array( $event_id, $ticket_id, $data );
                }
            }


            //recorremos el listado de attendees de este evento y lo ingresamos al listado general de attendees
            //Esto lo hacemos porque puede haber transacciones que incluyan compras en más de un evento.
            foreach ($attendee AS $val) {
                $attendees[] = $val;
            }
        }

        if (!empty($attendees) AND is_array($attendees)) {
	        //actualizamos a firestore

            $attendees_order_meta = [];

            try {
	            foreach ( $attendees AS $attendee ) {
                    //Send a new registrant to Firebase, using the documentId to the UUID stored in the idCardNumber key
                    $newDocument = $this->firestore->setDocument('registrants/' . $attendee['idCardNumber'], $attendee, false);

                    $newDocId = explode( '/', $newDocument->getRelativeName() );
                    $newDocumentId = end( $newDocId );

		            $attendees_order_meta[$newDocumentId] = $attendee['fullName'];
                }
            } catch ( \Exception $e ) {
	            WodenEvents::log( $e->getMessage(), null, 'Firebase' );
            }

            if (! empty( $attendees_order_meta ) ) {
                update_post_meta( $order_id, '_wodenevents_firestore_ids', $attendees_order_meta );

                WodenEvents::log( count( $attendees_order_meta ) . ' attendees de la orden #' . $order_id . ' enviados a Firestore' );
            } else {
                WodenEvents::log( 'Nada para enviar a Firestore de la orden #' . $order_id );
            }
        }
    }

    /** @throws \Exception
     * @var \WC_Order $order
     */
    public function check_unique_email_addresses( $order ) {
        $items = $order->get_items();

        foreach ( $items AS $item ) {
            $registrant_email = '';

            $product = wc_get_product( $item->get_product_id() );

            $unique_email = get_post_meta( $product->get_id(), '_wodenevents_unique_email', true );

            $product_category_ids = $product->get_category_ids();

            $event_id = $product->get_meta('_wodenevents_event2_id');
            $ticket_id = $product->get_meta('_wodenevents_ticket_id');

            if ( $unique_email === 'yes' && is_array( $product_category_ids ) && in_array( get_option( 'wodenevents_category_id' ), $product_category_ids ) ) {
                try {
                    $registrants = $this->runQuery(
                        'registrants',
                        [ 'email' ],
                        [
                            [ 'email', 'EQUAL', strtolower( trim( $order->get_billing_email() ) )],
                            [ 'eventId', 'EQUAL', $event_id ],
                            [ 'ticketId', 'EQUAL', $ticket_id ],
                            [ 'actualState.type', 'EQUAL', 'registered' ]
                        ],
                        [],
                        1
                    );

                    // We got a server response
                    if ( is_array($registrants) && !empty($registrants) ) {
                        /** @var FirestoreDocument $registrant */
                        $registrant = reset($registrants);

                        $registrant_email = $registrant->get( 'email' );
                    }
                } catch( \Exception $e ) {
                    continue;
                }

                if ( !empty( $registrant_email ) ) {
                    throw new \Exception( sprintf( __( 'This email address has been used before for the event %s. Please use a different one.', 'wodenevents' ), $product->get_title() ) );
                }
           }
        }
	}

	public function enqueue_styles() {
		//wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/plugin-name-admin.css', array(), $this->version, 'all' );
	}

	public function enqueue_scripts() {
		//wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/plugin-name-admin.js', array( 'jquery' ), $this->version, false );
	}

	public static function get_associated_events()
    {
	    $args = array(
		    'post_type' => 'product',
		    'posts_per_page' => -1,
		    'meta_query' => array(
			    array(
				    'key' => '_wodenevents_ticket_id',
				    'compare' => 'EXISTS',
			    )
		    )
	    );
	    $the_query = new \WP_Query( $args );

	    $event_ids = array();

	    if ( $the_query->have_posts() ) {
		    while ( $the_query->have_posts() ) {
			    $the_query->the_post();

    		    $event_ids[get_post_meta( get_the_ID(), "_wodenevents_ticket_id", true)] = array(
			                        'post_id' => get_the_ID(),
                                    'title' => get_the_title()
                                );
		    }
		    /* Restore original Post Data */
		    wp_reset_postdata();
	    }

	    wp_reset_query();

	    return $event_ids;
    }

    public static function get_product_by_associated_ticket_id($ticket_id)
    {
	    $args = array(
		    'post_type' => 'product',
		    'meta_query' => array(
			    array(
				    'key' => '_wodenevents_ticket_id',
				    'value' => $ticket_id,
				    'compare' => '=',
			    )
		    )
	    );
	    $the_query = new \WP_Query( $args );

	    if ( $the_query->have_posts() ) {
	        return $the_query->get_posts()[0];
	    }

	    return false;
    }

    /**
     * Esta funcion obtiene el Id de un objeto (en nuestra necesidad una categoria) de Wordpress con WPML
     *
     * https://wpml.org/documentation/support/creating-multilingual-wordpress-themes/language-dependent-ids/
     *
     * @param $object_id
     * @param $type
     * @return array|false
     */
    public static function lang_object_ids( $object_id, $type ) {
        if ( ! function_exists('icl_object_id') ) {
            return false;
        }

        global $sitepress;
        $default_language = $sitepress->get_default_language();

        if( is_array( $object_id ) ) {
            $translated_object_ids = array();

            foreach ( $object_id as $id ) {
                $translated_object_ids[] = apply_filters( 'wpml_object_id', $id, $type, false, $default_language );
            }
            return $translated_object_ids;
        } else {
            return apply_filters( 'wpml_object_id', $object_id, $type, false, $default_language );
        }
    }

    /**
     * Construye un array acorde a como se espera en Firebase. Si $data no tiene los campos
     * requeridos, intenta recrearlos o al menos dejarlos seteados para que no haya problemas
     *
     * @param string $event_id
     * @param string $ticket_id
     * @param array $data
     *
     * @return array|null
     * @throws \Exception
     */
    private function _build_registrant_array( $event_id, $ticket_id, $data ) {
        $current_time = new FirestoreTimestamp();

        if ( ! isset( $data['email'] ) ) {
            WodenEvents::log( '$data no contiene email.' );
            return null;
        }

        $actualState = new FirestoreObject(['date' => $current_time, 'type' => 'registered']);

        $return = [
            'idCardNumber' => uuid_create(UUID_TYPE_RANDOM),
            'eventId' => $event_id,
            'ticketId' => $ticket_id,
            'actualState' => $actualState,
            'creationDate' => $current_time,
            'email' => strtolower( trim( $data['email'] ) ),
            'fullName' => $data['fullName'],
            'phone' => $data['phone'],
            'currency' => $data['currency'],
            'price' => $data['price'],
            'stateHistory' => [$actualState],
            'lastModified' => $current_time,

            'source' => (string) $_SERVER['HTTP_HOST'],
            'paymentMethod' => $data['paymentMethod'],
            'paymentMethodTitle' => $data['paymentMethodTitle'],

            /*campos por defecto*/
            'reference' => 0,
            'selected' => false,
            'status' => 0
        ];

        return $return;
    }

    private function runQuery($collection, $fields, $where, $orderBy = [], $limit = null)
    {
        $fieldPath = [];
        foreach ($fields AS $field)
        {
            $fieldPath[] = [
                'fieldPath' => $field
            ];
        }

        $query = [
            'structuredQuery' => [
                'select' => [
                    'fields' => $fieldPath
                ],
                'from' => [
                    0 => [
                        'collectionId' => $collection,
                    ],
                ],
            ],
        ];

        if (isset($where[0]) && !is_array($where[0]))
        {
            $query['structuredQuery']['where'] = [
                'fieldFilter' => [
                    'field' => [
                        'fieldPath' => $where[0],
                    ],
                    'op' => $where[1],
                    'value' => [
                        'stringValue' => $where[2],
                    ],
                ],
            ];
        } else {
            $query['structuredQuery']['where'] = [
                'compositeFilter' => [
                    'op' => 'AND'
                ],
            ];

            foreach($where AS $whereSegment)
            {
                $query['structuredQuery']['where']['compositeFilter']['filters'][] = [
                    'fieldFilter' => [
                        'field' => [
                            'fieldPath' => $whereSegment[0],
                        ],
                        'op' => $whereSegment[1],
                        'value' => [
                            'stringValue' => $whereSegment[2],
                        ],
                    ],
                ];
            }
        }

        if (is_array($orderBy) and !empty($orderBy))
        {
            $query['structuredQuery']['orderBy'] = [
                'field' => [
                    'fieldPath' => $orderBy[0],
                ],
                'direction' => $orderBy[1],
            ];
        }

        if ( $limit !== null && is_numeric( $limit ) ) {
            $query['structuredQuery']['limit'] = $limit;
        }

        $response = $this->firestore->request('POST', 'documents/:runQuery', ['body' => json_encode($query)]);

        $documents = array_map(function($doc) {
            return new FirestoreDocument($doc['document']);
        }, $response);

        return $documents;
    }

}