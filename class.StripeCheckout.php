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
 *  + Data is sent to an endpoint within WordPress, and WordPress uses the Stripe API to 
 *    make the charge.
 *
 *
 * The endpoint for handling the token is defined in: TODO
 *
 *
 *
 */

require_once(ABSPATH . 'vendor/stripe/stripe-php/lib/Stripe.php');
require_once(WPMP_BASE_DIR . '/libs/class.order.php');

add_action('parse_request', 'parse_checkout_request');
// TEST
//
//  submissino results in something like:
// array(3) { ["stripeToken"]=> string(28) "tok_15KdcZAGP9Cgrd9djsKycGVi" ["stripeTokenType"]=> string(4) "card" ["stripeEmail"]=> string(22) "ryan.txanson@gmail.com" }


// TODO: should this go in verify payment func? 
//
function parse_checkout_request() {

     if($_SERVER["REQUEST_URI"] == '/stripe/checkout') {

          $settings = maybe_unserialize(get_option('_wpmp_settings'));
  
          $TestMode =  $settings['StripeCheckout']['stripe_mode'];

          $StripeProdAPIKey = $settings['StripeCheckout']['stripe_prod_api_key'];
          
          if($settings['StripeCheckout']['stripe_mode']=='sandbox') {
              $StripeMode = "TEST MODE: ";
              $StripeKey = $settings['StripeCheckout']['stripe_test_api_key'];
          } else {
              $StripeKey = $settings['StripeCheckout']['stripe_prod_api_key'];
          }
    
          $token = $_POST['stripeToken'];


          // data-name='{$this->Business}'
          // data-currency='{$this->Currency}'
          // data-amount='{$this->Amount}'>
          // data-description='Invoice. {$this->InvoiceNo}, {$this->OrderTitle}'
          // data-email'{$this->ClientEmail}'
          
          // try {
          //     $charge = Stripe_Charge::create(array(
          //       "amount" => 100, // TODO: amount in cents, again
          //       "currency" => "usd",
          //       "card" => $token,
          //       "description" => "payinguser@example.com")
          //     );
          // } catch(Stripe_CardError $e) {
          //     // The card has been declined
          // }

          echo "<h1>TEST</h1>";
          echo var_dump($_POST);
          // Create charge
          // TODO: where does WP Marketplace want us to redirect to for success or failure? 
          //
          exit();
     }

}

function order_amount_to_cents($string) {
    $float_val = (float) $string;
    $float_val_cents = $float_val * 100;
    $float_val_str = (string) $float_val_cents;
    return $float_val_cents;
}

if(!class_exists('StripeCheckout')){

class StripeCheckout extends CommonVers{
    var $TestMode;
    
    // TODO: 
    var $GatewayUrl = "http://www.Stripe.com/cgi-bin/webscr";
    // TODO: 
    var $GatewayUrl_TestMode = "http://www.sandbox.Stripe.com/cgi-bin/webscr";

    var $Business;
    var $StripeTestAPIKey;
    var $StripeProdAPIKey;
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

        if($TestMode==1) {
            $this->GatewayUrl = $this->GatewayUrl_TestMode;
        }
        
        $settings = maybe_unserialize(get_option('_wpmp_settings'));
        $this->Enabled= isset($settings['StripeCheckout']['enabled'])?$settings['StripeCheckout']['enabled']:"";
        $this->ReturnUrl = $settings['StripeCheckout']['return_url'];
        //$this->NotifyUrl = home_url('/Stripe/notify/');
        $this->NotifyUrl = home_url('?action=wpmp-payment-notification&class=StripeCheckout');
        $this->CancelUrl = $settings['StripeCheckout']['cancel_url'];
        $this->StripeTestAPIKey = $settings['StripeCheckout']['stripe_test_api_key'];
        $this->StripeProdAPIKey = $settings['StripeCheckout']['stripe_prod_api_key'];
        $this->Business =  $settings['StripeCheckout']['stripe_email'];
        $this->TestMode =  $settings['StripeCheckout']['stripe_mode'];
        //$this->Currency =  $settings['StripeCheckout']['currency'];
        $this->Currency =  get_option('_wpmp_curr_name','USD');
        
        if($settings['StripeCheckout']['stripe_mode']=='sandbox') {
            $this->GatewayUrl = $this->GatewayUrl_TestMode;
        }

        if($settings['StripeCheckout']['stripe_mode']=='sandbox') {
            $this->StripeAPIKey = $this->StripeTestAPIKey;
            $this->StripeMode = "TEST MODE: ";
        } else {
            $this->StripeAPIKey = $this->StripeProdAPIKey;
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
<tr><td>'.__("Cancel Url:","wpmarketplace").'</td><td><input type="text" name="_wpmp_settings[StripeCheckout][cancel_url]" value="'.$this->CancelUrl.'" /></td></tr>
<tr><td>'.__("Stripe Test Publishable Key:","wpmarketplace").'</td><td><input type="text" name="_wpmp_settings[StripeCheckout][stripe_test_api_key]" value="'.$this->StripeTestAPIKey.'" /></td></tr>
<tr><td>'.__("Stripe Prod Publishable Key:","wpmarketplace").'</td><td><input type="text" name="_wpmp_settings[StripeCheckout][stripe_prod_api_key]" value="'.$this->StripeProdAPIKey.'" /></td></tr>
<tr><td>'.__("Return Url:","wpmarketplace").'</td><td><input type="text" name="_wpmp_settings[StripeCheckout][return_url]" value="'.$this->ReturnUrl.'" /></td></tr>

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
        // TODO: action='/stripe/checkout' ? 
        $Form = " 

                    <form action='/checkout?action=wpmp-payment-notification&class=Stripe' method='POST' name='_wpdm_bnf_{$this->InvoiceNo}' id='_wpdm_bnf_{$this->InvoiceNo}'>
                      <script
                        src='http://checkout.stripe.com/checkout.js' class='stripe-button'
                        data-key='{$this->StripeAPIKey}'
                        data-image='/square-image.png'
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
    
    
    // TODO: create Stripe payment here, return true or false depending on whether it 
    // succeeded
    //
    // for stripe, need to create a payment, but do i need ot assume 
    // verifypayment will be re-run from time to time for things that were 
    // unable to be verified? Also, will the token returned from stripe.js be 
    // stored somewhere here where we can get to it? 
    function VerifyPayment() {

          $stripe_verified = false;

          Stripe::setApiKey($this->StripeAPIKey);
          echo "logging\n";

          // order_id
          $this->StripeToken = $_POST['stripeToken'];

          error_log(var_dump($_POST));

            
        // is this post data available here now? 
        // array(3) { ["stripeToken"]=> string(28) "tok_15KdcZAGP9Cgrd9djsKycGVi" ["stripeTokenType"]=> string(4) "card" ["stripeEmail"]=> string(22) "ryan.txanson@gmail.com" }

          echo var_dump($_POST);
          echo "\n";

          // order_amount is a string of numbers, 
          // logging array(7) { ["custom"]=> string(13) "54b7057889cc1" ["invoice"]=> string(13) "54b7057889cc1" ["action"]=> string(25) "wpmp-payment-notification" ["class"]=> string(14) "StripeCheckout" ["stripeToken"]=> string(28) "tok_15L1DhAGP9Cgrd9d4fWsud6V" ["stripeTokenType"]=> string(4) "card" ["stripeEmail"]=> string(22) "ryan.txanson@gmail.com" } array(7) { ["custom"]=> string(13) "54b7057889cc1" ["invoice"]=> string(13) "54b7057889cc1" ["action"]=> string(25) "wpmp-payment-notification" ["class"]=> string(14) "StripeCheckout" ["stripeToken"]=> string(28) "tok_15L1DhAGP9Cgrd9d4fWsud6V" ["stripeTokenType"]=> string(4) "card" ["stripeEmail"]=> string(22) "ryan.txanson@gmail.com" } object(stdClass)#144 (15) { ["order_id"]=> string(13) "54b7057889cc1" ["title"]=> string(0) "" ["date"]=> string(10) "1421280632" ["items"]=> string(16) "a:1:{i:0;i:604;}" ["cart_data"]=> string(142) "a:1:{i:604;a:4:{s:8:"quantity";i:1;s:9:"variation";a:2:{i:0;s:1:"1";i:1;s:13:"1418640831817";}s:5:"price";s:5:"49.00";s:8:"discount";s:0:"";}}" ["total"]=> string(5) "53.72" ["order_status"]=> string(10) "Processing" ["payment_status"]=> string(10) "Processing" ["uid"]=> string(1) "1" ["order_notes"]=> string(0) "" ["payment_method"]=> string(14) "StripeCheckout" ["shipping_method"]=> string(21) "Flat Rate (Minnesota)" ["shipping_cost"]=> string(4) "4.72" ["billing_shipping_data"]=> string(583) "a:2:{s:7:"billing";a:11:{s:10:"first_name";s:4:"Ryan";s:9:"last_name";s:7:"Johnson";s:7:"company";s:4:"asdf";s:9:"address_1";s:18:"1234 Mulberry Lane";s:9:"address_2";s:0:"";s:4:"city";s:7:"St Paul";s:8:"postcode";s:5:"55401";s:7:"country";s:2:"US";s:5:"state";s:9:"Minnesota";s:5:"email";s:22:"ryan.txanson@gmail.com";s:5:"phone";s:12:"651-690-5432";}s:10:"shippingin";a:9:{s:10:"first_name";s:4:"Ryan";s:9:"last_name";s:7:"Johnson";s:7:"company";s:0:"";s:9:"address_1";s:0:"";s:9:"address_2";s:0:"";s:4:"city";s:0:"";s:8:"postcode";s:0:"";s:7:"country";s:0:"";s:5:"state";s:0:"";}}" ["cart_discount"]=> string(1) "0" } string(5) "53.72" float(5372)
          // echo var_dump($this->order_amount);
          // echo "\n";
          // echo var_dump($order_amount_cents);
          // echo "\n";

          $order_desc = "Invoice. " . $this->order_info->invoice . " to " . $this->order_info->email;

          var $stripe_order = array(
                "amount" => order_amount_to_cents($this->order_amount),
                "currency" => "usd",
                "card" => $this->StripeToken,
                "description" => $order_desc,
          );

          echo var_dump($stripe_order);
          echo "\n";
              
          
          try {
              $charge = Stripe_Charge::create();
          } catch(Stripe_CardError $e) {
              // The card has been declined
          }

          die();

          if ($stripe_verified) {
             return true;       
          } else {
             $this->VerificationError = 'Unable to process payment.';             
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
