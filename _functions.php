<?php

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
