<?php

/* This is a stripe gateway for WP Marketplace. If you add this to your 
 * payment_methods/ it should automatically show up in the WP Marketplace 
 * configuration. Then, define your API keys and choose whether you're in test 
 * or production mode.
 *
 * How the process works:
 *
 *  + User fills out form
 *  + User clicks on the Stripe submit button
 *  + User enters card information
 *  + Stripe creates a payment token (and does not yet make the charge)
 *  + Data is sent to an endpoint within WordPress, and WordPress uses the 
 *    Stripe API to make the charge. (most of this happens in `VerifyNotification`)
 *
 */

require_once(ABSPATH . 'vendor/stripe/stripe-php/lib/Stripe.php');
require_once(WPMP_BASE_DIR . '/libs/class.order.php');

function order_amount_to_cents($string) {
    $float_val = (float) $string;
    $float_val_cents = $float_val * 100;
    $float_val_str = (string) $float_val_cents;
    return $float_val_cents;
}

if(!class_exists('StripeCheckout')){

class StripeCheckout extends CommonVers{
    var $TestMode;
    
    var $Business;
    var $StripeTestAPIKey;
    var $StripeProdAPIKey;
    var $StripeTestSecretAPIKey;
    var $StripeProdSecretAPIKey;
    var $StripeMode;
    var $ReturnUrl;
    var $NotifyUrl;
    var $CancelUrl;    
    var $Custom;
    var $Enabled;
    var $Currency;
    var $Ship_method;
    var $Ship_amount;
    var $Ship_currency;
    var $order_id;
    
    
    function StripeCheckout($TestMode = 0){
        $this->TestMode = $TestMode;                

        $settings = maybe_unserialize(get_option('_wpmp_settings'));
        $this->Enabled= isset($settings['StripeCheckout']['enabled'])?$settings['StripeCheckout']['enabled']:"";
        $this->NotifyUrl = home_url('?action=wpmp-payment-notification&class=StripeCheckout');
        $this->StripeTestAPIKey = $settings['StripeCheckout']['stripe_test_api_key'];
        $this->StripeProdAPIKey = $settings['StripeCheckout']['stripe_prod_api_key'];
        $this->StripeTestSecretAPIKey = $settings['StripeCheckout']['stripe_test_secret_api_key'];
        $this->StripeProdSecretAPIKey = $settings['StripeCheckout']['stripe_prod_secret_api_key'];
        $this->Business =  $settings['StripeCheckout']['stripe_email'];
        $this->TestMode =  $settings['StripeCheckout']['stripe_mode'];
        // TODO: currency
        //$this->Currency =  $settings['StripeCheckout']['currency'];
        $this->Currency =  get_option('_wpmp_curr_name','USD');
        
        if($settings['StripeCheckout']['stripe_mode']=='sandbox') {
            $this->StripeAPIKey = $this->StripeTestAPIKey;
            $this->StripeSecretAPIKey = $this->StripeTestSecretAPIKey;
            $this->StripeMode = "TEST MODE: ";
        } else {
            $this->StripeAPIKey = $this->StripeProdAPIKey;
            $this->StripeSecretAPIKey = $this->StripeProdSecretAPIKey;
        }
    }
    
    
    // TODO: 
    function ConfigOptions(){    
        
        
        if($this->Enabled)$enabled='checked="checked"';
        else $enabled = "";
        
        $data='<table>
<tr><td>'.__("Enable/Disable:","wpmarketplace").'</td><td><input type="checkbox" value="1" '.$enabled.' name="_wpmp_settings[StripeCheckout][enabled]" style=""> '.__("Enable Stripe","wpmarketplace").'</td></tr>
<tr><td>'.__("Stripe Mode:","wpmarketplace").'</td><td><select id="stripe_mode" name="_wpmp_settings[StripeCheckout][stripe_mode]"><option value="live">Live</option><option value="sandbox" >SandBox</option></select></td></tr>
<tr><td>'.__("Stripe Email:","wpmarketplace").'</td><td><input type="text" name="_wpmp_settings[StripeCheckout][stripe_email]" value="'.$this->Business.'" /></td></tr>
<tr><td>'.__("Stripe Test Publishable Key:","wpmarketplace").'</td><td><input type="text" name="_wpmp_settings[StripeCheckout][stripe_test_api_key]" value="'.$this->StripeTestAPIKey.'" /></td></tr>
<tr><td>'.__("Stripe Prod Publishable Key:","wpmarketplace").'</td><td><input type="text" name="_wpmp_settings[StripeCheckout][stripe_prod_api_key]" value="'.$this->StripeProdAPIKey.'" /></td></tr>
<tr><td>'.__("Stripe Test Secret Key:","wpmarketplace").'</td><td><input type="text" name="_wpmp_settings[StripeCheckout][stripe_test_secret_api_key]" value="'.$this->StripeTestSecretAPIKey.'" /></td></tr>
<tr><td>'.__("Stripe Prod Secret Key:","wpmarketplace").'</td><td><input type="text" name="_wpmp_settings[StripeCheckout][stripe_prod_secret_api_key]" value="'.$this->StripeProdSecretAPIKey.'" /></td></tr>

</table>
<script>
select_my_list("stripe_mode","'.$this->TestMode.'");
</script>
';
        return $data;
    }
    
    // TODO: stripe payment received confirmation webhooks
    // TODO: convert Amount to cents
                        
    function ShowPaymentForm($AutoSubmit = 0){
        
        $amount_cents = order_amount_to_cents($this->Amount);
        // TODO: https for stripe include
        $Stripe = plugins_url().'/wpdm-premium-packages/images/Stripe.png';
        // TODO: ClientEmail?
        $Form = " 

                    <form action='/checkout?action=wpmp-payment-notification&class=StripeCheckout' method='POST' name='_wpdm_bnf_{$this->InvoiceNo}' id='_wpdm_bnf_{$this->InvoiceNo}'>
                      <script
                        src='//checkout.stripe.com/checkout.js' class='stripe-button'
                        data-key='{$this->StripeAPIKey}'
                        data-image='/wp-content/uploads/2014/02/Crown.png'
                        data-name='{$this->Business}'
                        data-currency='{$this->Currency}'
                        data-amount='{$amount_cents}'>
                        data-description='Invoice. {$this->InvoiceNo}, {$this->OrderTitle}'
                        data-email'{$this->ClientEmail}'
                      </script>
                      <input type='hidden' name='custom' value='{$this->Custom}' />
                      <input type='hidden' name='invoice' value='{$this->InvoiceNo}' />
                      <input type='hidden' name='action' value='wpmp-payment-notification' />
                      <input type='hidden' name='class' value='StripeCheckout' />
                    </form>
        ";
        
        return $Form;
        
        
    }
    
    
    // For stripe, need to create a payment, this function is triggered by the URL parameters 
    // ?action=wpmp-payment-notification&class=StripeCheckout, which seems to 
    // be somewhat insecure; as users could modify this. TODO: is there a way to improve wpmp? 
    //
    function VerifyPayment() {

          $stripe_verified = false;

          Stripe::setApiKey($this->StripeSecretAPIKey);

          $this->StripeToken = $_POST['stripeToken'];
          $this->StripeEmail = $_POST['stripeEmail'];

          global $current_user; 

          // this extracts address to $shippingin and $billing. Surprise new 
          // variables not from a function return!
          //
          $usermeta=unserialize(get_user_meta($current_user->ID, 'user_billing_shipping',true));
          @extract($usermeta);

          // only way to tell if shipping is billing addr.
          if ($shippingin['country'] && $shippingin['postcode']) {
              $address = $shippingin;
          } else {
              $address = $billing;
          }

          $order_desc = "Invoice. " . $this->order_info->order_id . " to " . $billing["first_name"] . " " . $billing["last_name"] . " - " . $this->StripeEmail;

          // TODO: possible to include URL to invoice in WP ? we probably want 
          // more descriptive info here: all parts, etc.
          //
          $stripe_order = array(
                "amount" => order_amount_to_cents($this->order_amount),
                "currency" => "usd",
                "card" => $this->StripeToken,
                "description" => $order_desc,
                "shipping" => array(
                    "address" => array(
                        "line1" => $address["address_1"],
                        "line2" => $address["address_2"],
                        "city" => $address["city"],
                        "country" => $address["country"],
                        "postal_code" => $address["postcode"],
                        "state" => $address["state"],
                    ),
                    "name" => $billing["first_name"] . " " . $billing["last_name"],
                ),
          );

          try {
              $charge = Stripe_Charge::create($stripe_order);
              $stripe_verified = true;
          } catch(Stripe_CardError $e) {
              // TODO: what to dump?
              // The card has been declined
              $this->VerificationError = var_dump($e);
          }

          if ($stripe_verified) {
              return true;       
          } else {
              return false;
          }
   }
   
   function VerifyNotification() {
       if ($_POST) {
           $this->order_id = $_POST['invoice'];

           $order = new Order();
           $this->order_info = $order->GetOrder($this->order_id);
           $this->order_amount = $this->order_info->total;

           return $this->VerifyPayment();
       } else { 
           die("Problem occured in payment.");
       }
   }
    
    
}

}
?>
