<?php

// Shortcode donation
function donationForm($atts)
{
  $allowedPostFields = gbs_allowedPostFields();
  
  //TODO validation
  /*$requiredFieldsValidators = array(
      "firstname" => array(),
      "lastname" => array(),
      "amount" => array("numeric"),
      "currency" => array("currency"),
      "address1" => array(),
      "address2" => array(),
      "country" => array(),
      "email" => array("email"),
      "payment" => array("numeric"),
  );*/

  $a = shortcode_atts( array('action' => false), $atts );
  $action = get_permalink( $a["action"] );



  // when form has been sent...
  if ($_POST) {
      // Won't happen. See wp_ajax_donate hook
  }
  
  ob_start();

  //TODO position the div below with shortcode arguments
?>

<div>

<!-- the form -->
<form action="<?= admin_url( 'admin-ajax.php' ) ?>" method="post" id="donationForm">
  <input type="hidden" name="action" value="donate"> <!-- ajax key -->
  <!-- scrollable root element -->
  <div id="wizard">
    <!-- <h2>Spenden</h2> -->
    <!-- status bar -->
    <div id="status" class="row">
        <ol>
            <li class="col-xs-4 active"><span>1</span> Betrag</li>
            <li class="col-xs-4"><span>2</span> Zahlung</li>
            <li class="col-xs-4"><span>3</span> Bestätigung</li>
        </ol>
    </div>
 
    <!-- scrollable items -->
    <div id="donation-carousel" class="carousel slide" data-ride="carousel" data-interval="false" data-wrap="false" data-keyboard="false">
        <div class="carousel-inner" role="listbox">




          <!-- Amount -->
          <div class="item active">
            <div class="sr-only">
                <h3>Meine Spende</h3>
            </div>
            <div class="row">
                <div class="col-xs-12" id="donation-currency">
                    <div class="btn-group">
                        <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span id="selected-currency"><img src="<?= plugins_url('images/blank.gif', __FILE__) ?>" class="flag flag-eu" alt="€" /> EUR</a></span>
                            <span class="caret"></span>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a href="#"><img src="<?= plugins_url('images/blank.gif', __FILE__) ?>" class="flag flag-eu" alt="EUR" /> EUR</a></li>
                            <li><a href="#"><img src="<?= plugins_url('images/blank.gif', __FILE__) ?>" class="flag flag-ch" alt="CHF" /> CHF</a></li>
                            <li><a href="#"><img src="<?= plugins_url('images/blank.gif', __FILE__) ?>" class="flag flag-gb" alt="GBP" /> GBP</a></li>
                            <li><a href="#"><img src="<?= plugins_url('images/blank.gif', __FILE__) ?>" class="flag flag-us" alt="USD" /> USD</a></li>
                        </ul>
                    </div>
                    <input type="hidden" name="currency" value="EUR">
                </div>
            </div>
            <div class="row">
                <ul id="amounts" class="radio">
                  <li class="col-xs-4">
                    <input type="radio" class="radio" name="amount" value="15" tabindex="1" id="amount-15">
                    <label for="amount-15">15 €</label>
                  </li>
                  
                  <li class="col-xs-4">
                    <input type="radio" class="radio" name="amount" value="35" tabindex="2" id="amount-35">
                    <label for="amount-35">35 €</label>
                  </li>
                  
                  <li class="col-xs-4">
                    <input type="radio" class="radio" name="amount" value="50" tabindex="3" id="amount-50">
                    <label for="amount-50">50 €</label>
                  </li>
                  
                  <li class="col-xs-4">
                    <input type="radio" class="radio" name="amount" value="100" tabindex="4" id="amount-100">
                    <label for="amount-100">100 €</label>
                  </li>
                  
                  <li class="col-xs-4">
                    <input type="radio" class="radio" name="amount" value="250" tabindex="5" id="amount-250">
                    <label for="amount-250">250 €</label>
                  </li>
                  
                  <li class="col-xs-4">
                    <input type="radio" class="radio" name="amount" value="500" tabindex="6" id="amount-500">
                    <label for="amount-500">500 €</label>
                  </li>
                  
                  <li class="col-xs-4">
                    <input type="radio" class="radio" name="amount" value="1000" tabindex="7" id="amount-1000">
                    <label for="amount-1000">1000 €</label>
                  </li>
                  
                  <li class="col-xs-8">
                      <div class="input-group">
                          <span class="input-group-addon">€</span>
                          <input type="text" class="form-control input-lg text" name="amount_other" id="amount-other" placeholder="Anderer Betrag" tabindex="8">
                          <label for="amount-other" class="sr-only">Anderer Betrag</label>
                      </div>
                  </li>
                </ul>
            </div>
            <!--<div class="form-inline">
                <div class="form-group">
                    <label for="currency" class="control-label">Währung</label>
                    <select name="currency" id="currency" class="form-control">
                        <option value="EUR" selected="selected">€</option>
                        <option value="USD">$</option>
                        <option value="GBP">£</option>
                        <option value="CHF">CHF </option>
                    </select>
                </div>
            </div>-->
            <div class="buttons row">
                <div class="col-sm-4 col-sm-offset-4">
                    <button type="button" class="btn btn-success btn-lg confirm" disabled="disabled">Bestätigen »</button>
                </div>
            </div>

          </div>






          

        <!-- Payment -->
        <div class="item" id="payment-method">
            <div class="sr-only">
              <h3>Wählen Sie eine Zahlungsart</h3>
            </div>
            <!--
            <div class="checkbox alert alert-info">
                <label>
                    <input type="checkbox" name="recurring" value="1"> <span id="recurringDonationText">Monatlich spenden</span>.
                    <a href="#"><span>Mehr</span></a>
                </label>
            </div> -->
            <div class="required">
              <div class="radio">
                  <label for="payment-creditcard">
                      <input type="radio" class="radio" name="payment" value="Stripe" tabindex="18" id="payment-creditcard" checked="checked">
                      <img src="<?= plugins_url('images/visa.png', __FILE__) ?>" alt="Visa" width="38" height="23">
                      <img src="<?= plugins_url('images/mastercard.png', __FILE__) ?>" alt="Mastercard" width="38" height="23">
                      <img src="<?= plugins_url('images/americanexpress.png', __FILE__) ?>" alt="American Express" width="38" height="23">
                  </label>
              </div>
              <div class="radio">
                  <label for="payment-paypal">
                      <input type="radio" class="radio" name="payment" value="PayPal" tabindex="19" id="payment-paypal">
                      <img src="<?= plugins_url('images/paypal.png', __FILE__) ?>" alt="Paypal" width="38" height="23">
                  </label>
              </div>
              <div class="radio">
                  <label for="payment-skrill">
                      <input type="radio" class="radio" name="payment" value="Skrill" tabindex="21" id="payment-skrill">
                      <img src="<?= plugins_url('images/skrill.png', __FILE__) ?>" alt="Skrill" width="38" height="23">
                  </label>
              </div>
              <div class="radio">
                  <label for="payment-banktransfer">
                      <input type="radio" class="radio" name="payment" value="Banktransfer" tabindex="20" id="payment-banktransfer"> Banküberweisung
                  </label>
              </div>
              <!-- <div class="radio">
                  <label for="payment-directdebit">
                      <input type="radio" class="radio" name="payment" value="Lastschriftverfahren" tabindex="21" id="payment-directdebit">
                      Lastschriftverfahren
                  </label>
                  <div id="directdebit-details">
                      <div class="form-group form-group-sm">
                          <label for="payment-directdebit-frequency" class="control-label">Wie oft</label>
                          <select class="form-control" name="payment-directdebit-frequency" id="payment-directdebit-frequency">
                              <option value="monthly">monatlich</option>
                              <option value="quarterly">viertjährlich</option>
                              <option value="half-yearly">halbjährlich</option>
                              <option value="yearly">jährlich</option>
                          </select>
                      </div>
                      <div class="form-group form-group-sm">
                          <label for="payment-directdebit-account" class="control-label">Postkonto oder IBAN-Nummer</label>
                          <input class="form-control text" type="text" name="payment-directdebit-account" id="payment-directdebit-account">
                      </div>
                      <div class="form-group form-group-sm">
                          <label for="payment-directdebit-method" class="control-label">Spendenauftrag</label>
                          <select class="form-control" name="payment-directdebit-method" id="payment-directdebit-method">
                              <option value="print at home">zuhause ausdrucken</option>
                              <option value="post">per Post zusenden</option>
                          </select>
                      </div>
                  </div>
              </div> -->
              <!-- <li>
                <input type="radio" class="radio" name="payment" value="Banktransfer" tabindex="19" id="payment-bitpay">
                <label for="payment-bitpay">BitPay</label>
              </li> -->
            </div>

            <div class="buttons row">
                <div class="col-sm-6 col-sm-push-3">
                    <button type="button" class="btn btn-success btn-lg confirm" id="donationSubmit">Bestätigen »</button>
                </div>
                <div class="col-sm-3 col-sm-pull-6">
                    <button type="button" class="btn btn-link unconfirm" id="donationGoBack">« Zurück</button>
                </div>
            </div>
        </div>




        <!-- Confirmation -->
        <div class="item" id="donation-confirmation">
            <div class="alert alert-success">
                <div class="response-icon float-left">
                    <img src="<?= plugins_url('images/success.png', __FILE__) ?>" alt="Success" width="18" height="18">
                </div>
                <div class"response-text">
                    <strong>Herzlichen Dank!</strong> Wir haben Ihre Spende erhalten.
                </div>
            </div>
            <p class="alert alert-danger hidden"></p>
        </div>

      </div>
    </div>
  </div>

</form>

<div id="drawer">Bitte alle obligatorischen Felder ausfüllen.</div>


</div>

<?php
  $content = ob_get_clean();
  return $content;
}



