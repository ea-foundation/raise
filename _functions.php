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
          $_POST['amount'] = (int)($_POST['amount'] * 100);
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
            //handlePaypalPayment($_POST);
        } else if ($_POST['payment'] == "Skrill") {
            //FIXME
            //echo gbs_skrillRedirect($_POST);
        } else {
            throw new Exception('Payment method is invalid.');
        }
        
        die(json_encode(array(
            'success' => true,
        )));
    } catch (Exception $e) {
        die(json_encode(array(
            'success' => false,
            'error'   => "An error occured and your donation could not be processed (" .  $e->getMessage() . "). Please contact us at " . $GLOBALS['contactEmail'] . ".",
        )));
    }
}

function handleStripePayment($post)
{
    // Get the credit card details submitted by the form
    $token    = $post['stripeToken'];
    $amount   = $post['amount'];
    $currency = $post['currency'];
    $email    = $post['email'];

    // Create the charge on Stripe's servers - this will charge the user's card
    try {
        $customer = \Stripe\Customer::create(array(
            'email'  => $email,
            'source' => $token,
        ));

        $charge = \Stripe\Charge::create(
            array(
                'customer'    => $customer->id,
                'amount'      => $amount, // !!! in cents !!!
                'currency'    => $currency,
                'description' => 'Donation',
            )
        );

        // Prepare hook
        $donation = array(
            'time'     => date('r'),
            'currency' => $currency,
            'amount'   => money_format('%i', $amount / 100),
            'type'     => 'stripe',
            'email'    => $email,
        );

        // trigger hook for Zapier
        do_action('eas_log_donation', $donation);

        // Send email
        sendThankYouEmail($email);
    } catch(\Stripe\Error\InvalidRequest $e) {
        // The card has been declined
        throw new Exception($e->getMessage() . ' ' . $e->getStripeParam() . " : $email : $amount : $currency : $token");
    }
}

function getPaypalPayKey()
{
    try {
        $email     = $_POST['email'];
        $amount    = $_POST['amount'];
        $currency  = $_POST['currency'];
        $returnUrl = admin_url('admin-ajax.php');
        // Secret reference ID. Needed to prevent replay attack
        $reqId = uniqid();

        $qsConnector = strpos('?', $returnUrl) ? '&' : '?';
        $content = array(
            "actionType"      => "PAY",
            "returnUrl"       => $returnUrl . $qsConnector . "action=log&req=" . $reqId,
            "cancelUrl"       => $returnUrl . $qsConnector . "action=log",
            "requestEnvelope" => array(
                "errorLanguage" => "en_US",
                "detailLevel"   => "ReturnAll",
            ),
            "currencyCode"    => $currency,
            "receiverList"    => array(
                "receiver" => array(
                    array(
                        "email"  => $GLOBALS['paypalEmailId'],
                        "amount" => $amount,
                    )
                )
            )
        );
        $headers = array(
            'X-PAYPAL-SECURITY-USERID: '      . $GLOBALS['paypalApiUsername'],
            'X-PAYPAL-SECURITY-PASSWORD: '    . $GLOBALS['paypalApiPassword'],
            'X-PAYPAL-SECURITY-SIGNATURE: '   . $GLOBALS['paypalApiSignature'],
            'X-PAYPAL-DEVICE-IPADDRESS: '     . $_SERVER['REMOTE_ADDR'],
            'X-PAYPAL-REQUEST-DATA-FORMAT: '  . 'JSON',
            'X-PAYPAL-RESPONSE-DATA-FORMAT: ' . 'JSON',
            'X-PAYPAL-APPLICATION-ID: '       . 'APP-80W284485P519543T',
        );

        //die(json_encode($content));

        // Set Options for CURL
        $curl = curl_init($GLOBALS['paypalPayKeyEndpoint']);
        curl_setopt($curl, CURLOPT_HEADER, false);
        // Return Response to Application
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        // Execute call via http-POST
        curl_setopt($curl, CURLOPT_POST, true);
        // Set POST-Body
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($content));
        // Set headers
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        // WARNING: This option should NOT be "false"
        // Otherwise the connection is not secured
        // You can turn it of if you're working on the test-system with no vital data
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        // Load list file with up-to-date certificate authorities
        //curl_setopt($curl, CURLOPT_CAINFO, 'ssl/server.crt');
        // CURL-Execute & catch response
        $jsonResponse = curl_exec($curl);
        // Get HTTP-Status, abort if Status != 200 
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($status != 200) {
            die(json_encode(array(
                'success' => false,
                'error'   => "Error: Call to " . $GLOBALS['paypalPayKeyEndpoint'] . " failed with status $status, " .
                             "response " . $jsonResponse . ", curl_error " . curl_error($curl) . ", curl_errno " . 
                             curl_errno($curl) . ", HTTP-Status: " . $status,
            )));
        }
        // Close connection
        curl_close($curl);
        
        //Convert response into an array and extract the payKey
        $response = json_decode($jsonResponse, true);
        if ($response['responseEnvelope']['ack'] != 'Success') {
            die("Error: " . $response['error'][0]['message']);
        }

        // Put user data in session. This way we can avoid other people using it to spam our logs
        $_SESSION['eas-req-id']   = $reqId;
        $_SESSION['eas-email']    = $email;
        $_SESSION['eas-currency'] = $currency;
        $_SESSION['eas-amount']   = money_format('%i', $amount);

        // Return pay key
        die(json_encode(array(
            'success' => true,
            'paykey'  => $response['payKey'],
        )));
    } catch(Exception $e) {
        die(json_encode(array(
            'success' => false,
            'error'   => $e->getMessage(),
        )));
    }
}

function processPaypalLog()
{
    if (isset($_GET['req']) && $_GET['req'] == $_SESSION['eas-req-id']) {
        // Prepare hook
        $donation = array(
            "time"     => date('r'),
            "currency" => $_SESSION['eas-currency'],
            "amount"   => money_format('%i', $_SESSION['eas-amount']),
            "type"     => "paypal",
            "email"    => $_SESSION['eas-email'],
        );

        // Trigger hook for Zapier
        do_action('eas_log_donation', $donation);

        // Send email
        sendThankYouEmail($email);

        // Add method for showing confirmation
        $script = "parent.showConfirmation();";
    } else {
        // Add method for unlocking buttons for trying again
        $script = "parent.lockLastStep(false);";
    }

    // Die and send script to close flow
    die('<!doctype html>
         <html lang="en"><head><meta charset="utf-8"><title>Closing flow...</title></head>
         <body><script>' . $script . ' parent.embeddedPPFlow.closeFlow(); close();
         </script><body></html>');
}

function sendThankYouEmail($email)
{
    $subject = 'Thank you for your gift to EAS!';

    $message = '<strong>Hi there!</strong>';
    $message .= '<p>';
    $message .= 'This is an automatically generated email to confirm your donation to the EA Foundation.';
    $message .= '</p><p>';
    $message .= 'Thank you so much for supporting our mission!';
    $message .= '</p><p>';
    $message .= 'The EA Foundation team';
    $message .= '</p>';

    wp_mail($email, $subject, $message);
}

/*

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
    <input type="hidden" name="confirmation_note" value="The world just got a bit brighter. Thanks for supporting our effective charities! If you haven't already, don't forget to go to reg-charity.org and become a REG member. And don't forget: You can reach us anytime at info@reg-charity.org"> <!-- This is somehow not working -->
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
*/
























