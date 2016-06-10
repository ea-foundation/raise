<?php

function processDonation()
{
    try {
        // do some cosmetics on form data
        $keys = array_keys($_POST);

        // replace amount-other
        if (in_array('amount_other', $keys) && !empty($_POST['amount_other'])) {
          $_POST['amount'] = $_POST['amount_other'];
        }
        unset($_POST['amount_other']);

        // Convert amount to cents
        if (is_numeric($_POST['amount'])) {
          $_POST['amount'] = (int) ($_POST['amount'] * 100);
        } else {
          throw new Exception('Invalid amount.');
        }

        // add payment-details
        /*if (in_array('payment', $keys) && $_POST['payment'] == "Lastschriftverfahren") {
          $_POST['payment-details'] = $_POST['payment-directdebit-frequency'] . ' | '  . $_POST['payment-directdebit-bankaccount'] . ' | ' . $_POST['payment-directdebit-method'];
        } else {
          $_POST['payment-details'] = '';
        }
        unset($_POST['payment-directdebit-frequency']);
        unset($_POST['payment-directdebit-bankaccount']);
        unset($_POST['payment-directdebit-method']);*/
        
        // output
        if ($_POST['payment'] == "Stripe") {
            handleStripePayment($_POST);
        } else if ($_POST['payment'] == "PayPal") {
            //FIXME
            echo gbs_paypalRedirect($_POST);
        } else if ($_POST['payment'] == "Skrill") {
            //FIXME
            echo gbs_skrillRedirect($_POST);
        } else {
            throw new Exception('Payment method is invalid.');
        }

        die("success");
    } catch (Exception $e) {
        die("An error occured and your donation could not be processed (" .  $e->getMessage() . "). Please contact us at <a href='mailto:info@ea-stiftung.org'>info@ea-stiftung.org</a>.");
    }
}

function handleStripePayment($post)
{
    // Get the credit card details submitted by the form
    $email    = $post['stripeEmail'];
    $token    = $post['stripeToken'];
    $amount   = $post['amount'];
    $currency = $post['currency'];

    // Create the charge on Stripe's servers - this will charge the user's card
    try {
        $charge = \Stripe\Charge::create(
            array(
                "amount"      => $amount,
                "currency"    => $currency, // in cents
                "source"      => $token,
                "description" => "Donation",
            )
        );

        // Prepare hook
        $donation = array(
            "time"     => date('r'),
            "currency" => $currency,
            "amount"   => money_format('%i', $amount / 100),
            "type"     => "stripe",
            "email"    => $email,
        );

        // trigger hook for Zapier
        do_action('eas_log_donation', $donation);
    } catch(\Stripe\Error\Card $e) {
        // The card has been declined
        throw new Exception($e->getMessage());
    }
}

function gbs_allowedPostFields()
{
    return array(
        "amount",
        "amount_other",
        "currency",
        "title",
        "firstname",
        "lastname",
        "address1",
        "address2",
        "address3",
        "country",
        "email",
        "phone",
        "payment",
        "payment-details",
    );
}


function gbs_paypalRedirect($post)
{
    ob_start();
?>
    <p>You will be redirected to PayPal in a few seconds... If not, click <a href="javascript:document.getElementById('paypalRedirect').submit();">here</a>.</p>
    <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top" id="paypalRedirect">
    <input type="hidden" name="cmd" value="_donations">
    <input type="hidden" name="business" value="PTCLVP4Y26VVY">
    <input type="hidden" name="lc" value="CH">
    <input type="hidden" name="item_name" value="Raising for Effective Giving">
    <input type="hidden" name="currency_code" value="<?php echo $post['currency']; ?>">
    <input type="hidden" name="amount" value="<?php echo $post['amount']; ?>">
    <input type="hidden" name="first_name" value="<?php echo $post['firstname']; ?>">
    <input type="hidden" name="last_name" value="<?php echo $post['lastname']; ?>">
    <input type="hidden" name="email" value="<?php echo $post['email']; ?>">
    <input type="hidden" name="no_note" value="1">
    <input type="hidden" name="no_shipping" value="2">
    <input type="hidden" name="rm" value="1">
    <input type="hidden" name="return" value="http://reg-charity.org">
    
    <input type="hidden" name="bn" value="PP-DonationsBF:btn_donateCC_LG.gif:NonHosted">
    <input type="image" src="http://reg-charity.org/wp-content/uploads/2014/07/PayPal-Logo.png" border="0" name="submit" alt="Pay with PayPal." style="width: 382px; height: 63px;">
    <img alt="" border="0" src="https://www.paypalobjects.com/de_DE/i/scr/pixel.gif" width="1" height="1">
    </form>
    <script type="text/javascript">
        var form = document.getElementById("paypalRedirect");
        setTimeout(function() { form.submit(); }, 1000);
    </script>
<?php
    
    $content = ob_get_clean();
    // remove line breaks
    $content = trim(preg_replace('/\s+/', ' ', $content));
    return $content;
}





function gbs_skrillRedirect($post)
{
    ob_start();
?>
    <p>You will be redirected to Skrill in a few seconds... If not, click <a href="javascript:document.getElementById('skrillUSDRedirect').submit();">here</a>.</p>
    <form action="https://www.moneybookers.com/app/payment.pl" method="post" id="skrillUSDRedirect">
    <input type="hidden" name="pay_to_email" value="reg-skrill@gbs-schweiz.org">
    <input type="hidden" name="return_url" value="http://reg-charity.org">
    <input type="hidden" name="status_url" value="donate@gbs-schweiz.org">
    <input type="hidden" name="language" value="EN">
    <input type="hidden" name="amount" value="<?php echo $post['amount']; ?>">
    <input type="hidden" name="currency" value="<?php echo $post['currency']; ?>">
    <input type="hidden" name="pay_from_email" value="<?php $post['email']; ?>">
    <input type="hidden" name="firstname" value="<?php echo $post['firstname']; ?>">
    <input type="hidden" name="lastname" value="<?php echo $post['lastname']; ?>">
    <input type="hidden" name="confirmation_note" value="The world just got a bit brighter. Thanks for supporting our effective charities! If you haven't already, don't forget to go to reg-charity.org and become a REG member. And don't forget: You can reach us anytime at info@reg-charity.org"> <!--/* This is somehow not working */-->
    <input type="image" src="http://reg-charity.org/wp-content/uploads/2014/10/skrill-button.png" border="0" name="submit" alt="Pay by Skrill">
    </form>
    <script>
        var form = document.getElementById("skrillUSDRedirect");
        //setTimeout(function() { form.submit(); }, 1000);
    </script>
<?php
    $content = ob_get_clean();
    // remove line breaks
    $content = trim(preg_replace('/\s+/', ' ', $content));
    return $content;
}
