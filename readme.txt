=== WODEN Events WordPress Plugin ===
Contributors: woden, laneros
Requires at least: 4.9
Tested up to: 5.3.2
Stable tag: 1.2.3
Requires PHP: 5.6

== Description ==
The WODEN Events Wordpress Plugin allows you to integrate your WordPress and WooCommerce installation with the
WODEN Events service. Once you install and configure it, every time someone purchases a ticket from your site, it will
automatically be posted to the WODEN Events platform.

== Installation ==
1. Open a WODEN Events account <a href="https://admin.wodenevents.com/signup">here</a>, create event and ticket types.
2. Generate an API key by selecting Sales channels on the WODEN Events menu and click on "Add" Woocommerce. On the
   pop-up window, click Generate API Key and copy the generated key.
3. Install WODEN Events either via the WordPress.org plugin repository or by uploading the files to your server.
4. Navigate to the Settings area in the WordPress admin > Settings > WODEN Events, and activate the plugin by pasting
   the API key you copied from Step 2. A confirmation will appear in green when the connection is successful.
5. Choose the product category you use for your tickets on the shopping cart
6. Go to the product page and from the drop down menu choose the WODEN Event ticket to associate with the product.

== Frequently Asked Questions ==

= What technology is WODEN Events powered by? =

WODEN Events is mainly powered by the Google Cloud platform

= Help! I need support or have an issue. =

Please send an email to info@wodenevents.com with all your enquires.

= Can you add feature x, y or z to the plugin? =

We consider all the feature request for inclusion into our product. Please send an email to info@wodenevents.com with
your feature requests.

== Screenshots ==

1. Settings
2. Product page

== Changelog ==

= 1.2.3 - 2020-03-02 =
* NEW FEATURE: Allows you to specify if you want unique email addresses per event. This will prevent a purchase if the order email has been used before in the event

= 1.2.2 - 2020-02-24 =
* Fixed an uncaught exception when loading the list of events
* We don't depend on Firestore's auto-id generation and instead we use a UUID. This saves one call to the API when inserting registrants

= 1.2.1 - 2020-01-09 =
* Updated the name of the Creation Date field and added a new field with the Payment Method used to purchase the tickets

= 1.2 - 2019-05-23 =
* First version