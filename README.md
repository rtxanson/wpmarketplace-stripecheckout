# WP MarketPlace Stripe Checkout gateway

Incomplete as yet.

This uses [Stripe Checkout](https://stripe.com/docs/checkout), which is a two part process:

 * The javascript form is filled out, creates a single-use payment token
 * The payment verification function within WPMarketplace uses the single-use
   token to create the charge, providing Stripe with more customer information
   from the WP Marketplace plugin.

## Tested on...

 * WordPress 3.8
 * WP Marketplace 2.3.5

## Installing the dependencies

### Composer

Copy the dependency in `composer.json` to your own `composer.json` file, or
otherwise install that version of [Stripe's PHP API][stripeapi].

  [stripeapi]: https://stripe.com/docs/libraries

Run the composer install process.

### PHP Curl

Stripe requires PHP's cURL libray, and this is not provided in the Composer
install process in any way. Install this somehow.

## Installing the gateway

 * Locate the `wpmarketplace` plugin within your wordpress directory, probably: 
 
    YOUR_WORDPRESS_DIR/wp-content/plugins/wpmarketplace/

 * Change to the following:

    cd libs/payment_methods/

 * Check out this repo there into a directory with `StripeCheckout` as the name:

    git clone https://github.com/rtxanson/wpmarketplace-stripecheckout.git StripeCheckout

 * Confirm within WP Marketplace settings that the gateway is installed.

 * Configure as specified, and test.


## Problems

 * For some reason my version of WP Marketplace did not show a nice payment
   received thing at the end. This could be because my implementation is
   incomplete.

 * There are some TODOs to handle. 
