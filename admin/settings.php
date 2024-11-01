<?php

namespace WodenEvents\Admin;

use MrShan0\PHPFirestore\FirestoreDocument;
use WodenEvents\Includes\Guzzle\RefreshToken;
use WodenEvents\Includes\WodenEvents;

class Settings {

	private $plugin_name;

	private $version;

	private $firestore;

	public function __construct( $plugin_name, $version, $firestore ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;

		$this->firestore = $firestore;
	}

    public function setting_page() {
        add_options_page( 'WodenEventsOptions', 'WODEN Events', 'manage_options', 'wodenevents', [$this, 'options_page'] );
    }

    public function options_page() {
	    if ( isset( $_POST['wodenevents_api_key'] ) ) {
            if (!isset($_POST['wodenevents_nonce'])) {
                return;
            }

            check_admin_referer( 'options_page', 'wodenevents_nonce' );

            $wodenevents_api_key = sanitize_text_field( $_POST['wodenevents_api_key'] );
            //$wodenevents_different_api_key = get_option( 'wodenevents_firestore_api_key' ) !== $wodenevents_api_key;

            if ( isset( $_POST['wodenevents_event_category'] ) ) {
                update_option('wodenevents_category_id', sanitize_text_field( $_POST['wodenevents_event_category'] ) );
            }

            if ( ! empty( $wodenevents_api_key ) ) {
                update_option('wodenevents_firestore_api_key', $wodenevents_api_key );

                if ( $this->validate_api_key($wodenevents_api_key) ) {
                    wp_redirect( admin_url( 'options-general.php?page=wodenevents' ) );
                    exit;
                } else {
                    RefreshToken::resetFirestoreAuth();
                }
            } elseif ( isset( $_POST['wodenevents_api_key'] ) ) {
                RefreshToken::resetFirestoreAuth();
            }
        }

        $wodenevents_api_key = get_option( 'wodenevents_firestore_api_key' );
        $wodenevents_event_category = intval( get_option( 'wodenevents_category_id' ) );

?>
<div class="wrap">
    <img id="woden_logo" src="<?php echo plugins_url('../res/img/logo.png', __FILE__) ?>">
	<h1><?php echo __('WODEN Events Integration', 'wodenevents') ?></h1>

    <div id="welcome">
        <h2><?php echo __('About WODEN and our Plugin', 'wodenevents') ?></h2>
        <?php echo __('<p>WODEN Integration is a free plugin, our team maintains it to give back to the WordPress community.</p>
        <p>Sell your event tickets directly from your Woocommerce store. The plugin connects the product to the WODEN Event management
        platform, keeping track of your event progress, as well as helping you manage your sales, check-in process and electronic ticket generation in real time.</p>
        <p>We are working hard to improve the user experience as well as integrating other e-commerce platforms to WODEN. If you need any help, please contact our
        customer service we\'re always here to help!</p>
        <p>You can login to WODEN <a href="https://wodenevents.com/" target="_blank">here</a>. <strong>Thank you</strong> for trusting us.</p>', 'wodenevents') ?>
    </div>

	<form id="wodenevents_frm_options" method="post">
        <?php wp_nonce_field( 'options_page', 'wodenevents_nonce' ) ?>

        <table class="form-table">

	<tr>
	<th scope="row"><label for="wodenevents_api_key"><?php echo __('WODEN API Key', 'wodenevents') ?></label></th>
	<td>
		<input id="wodenevents_api_key" name="wodenevents_api_key" value="<?php echo $wodenevents_api_key ?>" />
        <p class="description"><?php echo __('The API Key to connect to your WODEN account. <a href="https://wodenevents.com">Get yours here</a>.') ?></p>
    </td>
	</tr>

<tr>
    <th scope="row"><label for="wodenevents_status"><?php echo __('Integration status', 'wodenevents') ?></label></th>
<?php
        try {
            $user_id = get_option('wodenevents_firestore_user_id');

            if ( empty( $user_id ) ) {
                //We use the API to let the middleware get the user_id. This is the simplest way of doing it.
                $this->firestore->listDocuments('companyFilters', [
                    'pageSize' => 1
                ]);

                $user_id = get_option('wodenevents_firestore_user_id');
            }

            $user = $this->firestore->getDocument('users/' . $user_id,
                ['query' => 'mask.fieldPaths=companyId'])
            ;

            $company = $this->firestore->getDocument('companies/' . $user->get('companyId'),
                ['query' => 'mask.fieldPaths=name']
            );
        } catch (\Exception $e) {
            $company = new FirestoreDocument();
            WodenEvents::log($e->getMessage());
        }

        if (($company->getName() !== null) ) {

            $args = array(
                'taxonomy'     => 'product_cat',
                'orderby'      => 'name',
                'show_count'   => 0,
                'pad_counts'   => 0,
                'hierarchical' => 1,
                'title_li'     => '',
                'hide_empty'   => 0
            );

            $all_categories = get_categories( $args );

?>

<td>
    <p id="connected"><strong><?php echo __('Connected', 'wodenevents') ?></strong></p>
</td>
</tr>
<tr>
<th scope="row"><label for="wodenevents_company"><?php echo __('Company', 'wodenevents') ?></label></th>
<td>
    <p><?php echo $company->get('name') ?></p>
</td>
</tr>
<tr>
<th scope="row"><label for="wodenevents_company"><?php echo __('Event category', 'wodenevents') ?></label></th>
<td>
    <select name="wodenevents_event_category">
        <option value="" disabled="disabled" <?php echo (! $wodenevents_event_category ? 'selected="selected"' : '') ?>><?php echo __('Select a category', 'wodenevents') ?>'</option>;

        <?php
        foreach ($all_categories AS $category) {
            if ('Uncategorized' === $category->name) {
                continue;
            }
            $select_extras = selected( $wodenevents_event_category, $category->term_id, false );
            echo '<option value="' . $category->term_id . '"' . $select_extras . '>' . $category->name . '</option>';
        }
        ?>
    </select>
    <p class="description"><?php echo __('The Woocommerce category where you plan to store all your events.') ?></p>
</td>
</tr>
<?php
        } else {
?>
<td>
    <p id="not-connected"><strong><?php echo __('Not Connected', 'wodenevents') ?></strong></p>
    <p class="description"><?php echo __('Make sure your API Key is valid.', 'wodenevents') ?></p>
    
</td>
</tr>
<?php
        }

?>
</table>
	<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo __('Save Changes', 'wodenevents') ?>"></p>
	</form>
</div>
<?php
    }

    public function action_links( $links ) {
        $links[] = '<a href="'. get_admin_url(null, 'options-general.php?page=wodenevents') .'">Settings</a>';
        $links[] = '<a href="https://admin.wodenevents.com/signup" target="_blank">WODEN Sign Up</a>';
        return $links;
    }

    /**
     * Queries our application to check that the API Key is associated to a valid user.
     * If so, authenticates with Firebase with it, saves the token Id and returns true
     *
     * @param $api_key
     * @return bool
     */
	public function validate_api_key($api_key) {
	    $client = new \GuzzleHttp\Client();

	    try {
            $customToken = $client->request('POST', WODEN_EVENTS_CREATE_TOKEN_ENDPOINT, [
                'json' => [ 'apiKey' => $api_key ]
            ]);

            if ( $customToken->getStatusCode() !== 200 ) {
                return false;
            }

            $customToken = \GuzzleHttp\json_decode((string)$customToken->getBody(), true);

            $id_token = $client->request('POST', 'https://www.googleapis.com/identitytoolkit/v3/relyingparty/verifyCustomToken', [
                'json' => [
                    'token' => $customToken['customToken'],
                    'returnSecureToken' => true
                ],
                'query' => ['key' => WODEN_EVENTS_WEB_API_KEY]
            ]);

            if ( $id_token->getStatusCode() !== 200 ) {
                return false;
            }

            $id_token = \GuzzleHttp\json_decode((string)$id_token->getBody(), true);

            update_option( 'wodenevents_firestore_id', $id_token['idToken'] );
            update_option( 'wodenevents_firestore_refresh', $id_token['refreshToken'] );
            update_option( 'wodenevents_firestore_expires_in', $id_token['expiresIn'] );

            return true;
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
	        WodenEvents::log( $e->getMessage() );
	        //We catch the error but we do nothing since we're already returning false in the next line
        }

        return false;
    }

    public function enqueue_styles($hook) {
        if ( $hook != 'settings_page_wodenevents' ) {
            return;
        }

        wp_enqueue_style( 'custom_wp_admin_css', plugins_url('../res/css/settings.css', __FILE__) );
        //wp_enqueue_style( 'font_awesome_css', 'https://use.fontawesome.com/releases/v5.8.2/css/all.css', [], null );
    }
}