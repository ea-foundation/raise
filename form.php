<?php

// Shortcode donation
function donationForm($atts)
{
    if (isset($_GET['success']) && $_GET['success'] == 'true') {
        // Set status to payed to prevent replay attacks on our logs
        $checkoutCssClass = '';
        $confirmationCssClass = ' active';
    } else {
        $checkoutCssClass = ' active';
        $confirmationCssClass = '';
    }
    ob_start();
?>

<div>

<!-- Donation form -->
<form action="<?= admin_url('admin-ajax.php') ?>" method="post" id="donationForm" class="form-horizontal">
  <input type="hidden" name="action" value="donate"> <!-- ajax key -->
  <!-- Scrollable root element -->
  <div id="wizard">
    <!-- Status bar -->
    <div id="status" class="row">
        <ol>
            <li class="col-xs-4<?= $checkoutCssClass ?>"><span>1</span> Betrag</li>
            <li class="col-xs-4"><span>2</span> Zahlung</li>
            <li class="col-xs-4<?= $confirmationCssClass ?>"><span>3</span> Bestätigung</li>
        </ol>
    </div>
 
    <!-- Scrollable items -->
    <div id="donation-carousel" class="carousel slide" data-ride="carousel" data-interval="false" data-wrap="false" data-keyboard="false">
        <div class="carousel-inner" role="listbox">



          <!-- Amount -->
          <div class="item<?= $checkoutCssClass ?>" id="amount-item">
              <div class="sr-only">
                  <h3>Meine Spende</h3>
              </div>
              <div class="row">
                  <div class="col-xs-12" id="donation-currency">
                      <div class="btn-group">
                          <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                              <img src="<?= plugins_url('images/blank.gif', __FILE__) ?>" id="selected-currency-flag" class="flag flag-eu" alt="EUR">
                              <span id="selected-currency">EUR</span>
                              <span class="caret"></span>
                          </button>
                          <ul class="dropdown-menu">
                              <li><label for="currency-eur"><input type="radio" id="currency-eur" name="currency" value="EUR" class="hidden" checked><img src="<?= plugins_url('images/blank.gif', __FILE__) ?>" class="flag flag-eu" alt="EUR">EUR</label></li>
                              <li><label for="currency-chf"><input type="radio" id="currency-chf" name="currency" value="CHF" class="hidden"><img src="<?= plugins_url('images/blank.gif', __FILE__) ?>" class="flag flag-ch" alt="CHF">CHF</label></li>
                              <li><label for="currency-gbp"><input type="radio" id="currency-gbp" name="currency" value="GBP" class="hidden"><img src="<?= plugins_url('images/blank.gif', __FILE__) ?>" class="flag flag-gb" alt="GBP">GBP</label></li>
                              <li><label for="currency-usd"><input type="radio" id="currency-usd" name="currency" value="USD" class="hidden"><img src="<?= plugins_url('images/blank.gif', __FILE__) ?>" class="flag flag-us" alt="USD">USD</label></li>
                          </ul>
                      </div>
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
              <div class="buttons row">
                  <div class="col-sm-4 col-sm-offset-4">
                      <button type="button" class="btn btn-success btn-lg confirm" disabled="disabled">Bestätigen »</button>
                  </div>
              </div>
          </div>






          

        <!-- Payment -->
        <div class="item" id="payment-method-item">
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
            <div class="form-group required payment-info">
                <div class="radio">
                    <label for="payment-creditcard">
                        <input type="radio" class="radio" name="payment" value="Stripe" tabindex="18" id="payment-creditcard" checked="checked">
                        <img src="<?= plugins_url('images/visa.png', __FILE__) ?>" alt="Visa" width="38" height="23">
                        <img src="<?= plugins_url('images/mastercard.png', __FILE__) ?>" alt="Mastercard" width="38" height="23">
                        <img src="<?= plugins_url('images/americanexpress.png', __FILE__) ?>" alt="American Express" width="38" height="23">
                    </label>
  
                    <label for="payment-paypal">
                        <input type="radio" class="radio" name="payment" value="PayPal" tabindex="19" id="payment-paypal">
                        <img src="<?= plugins_url('images/paypal.png', __FILE__) ?>" alt="Paypal" width="38" height="23">
                    </label>

                    <!-- <label for="payment-skrill">
                        <input type="radio" class="radio" name="payment" value="Skrill" tabindex="21" id="payment-skrill">
                        <img src="<?= plugins_url('images/skrill.png', __FILE__) ?>" alt="Skrill" width="38" height="23">
                    </label> -->

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

            <div class="form-group donor-info" id="donation-purpose">
                <label for="donor-email" class="col-sm-3 control-label">Verwendungszweck</label>
                <div class="col-sm-9">
                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span id="selected-purpose">Wo es am dringendsten benötigt wird</span>
                        <span class="caret"></span>
                    </button>
                    <ul class="dropdown-menu">
                        <?php
                            $checked = 'checked';
                            foreach ($GLOBALS['donationPurposes'] as $value => $label) {
                                echo '<li><label for="purpose-' . strtolower($value) . '"><input type="radio" id="purpose-' . strtolower($value) . '" name="purpose" value="' . $value . '" class="hidden" '  . $checked . '>' . htmlspecialchars($label) . '</label></li>';
                                $checked = '';
                            }
                        ?>
                    </ul>
                </div>
            </div>

            <div class="form-group required donor-info">
                <label for="donor-email" class="col-sm-3 control-label">E-Mail</label>
                <div class="col-sm-9">
                    <input type="email" class="form-control text" name="email" id="donor-email" placeholder="E-Mail Adresse">
                </div>
            </div>

            <div class="form-group donor-info">
                <div class="col-sm-offset-3 col-sm-9">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" id="tax-receipt" value="1"> Ich möchte einen Steuerbeleg für Deutschland oder die Schweiz.
                        </label>
                    </div>
                </div>
            </div>

            <!-- Donor extra info start -->
            <div id="donor-extra-info">
                <div class="form-group donor-info optionally-required">
                    <label for="donor-name" class="col-sm-3 control-label">Name</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control text" name="name" id="donor-name" placeholder="Monika Mustermann">
                    </div>
                </div>

                <div class="form-group donor-info optionally-required">
                    <label for="donor-address-1" class="col-sm-3 control-label">Adresse</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control text" name="address1" id="donor-address-1" placeholder="Kantstraße 71">
                    </div>
                </div>

                <div class="form-group donor-info optionally-required">
                    <label for="donor-zip" class="col-sm-3 control-label">Postleitzahl</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control text" name="zip" id="donor-zip" placeholder="10627">
                    </div>
                </div>

                <div class="form-group donor-info optionally-required">
                    <label for="donor-city" class="col-sm-3 control-label">Ort</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control text" name="city" id="donor-city" placeholder="Berlin">
                    </div>
                </div>

                <div class="form-group donor-info optionally-required">
                    <label for="donor-country" class="col-sm-3 control-label">Land</label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control text" name="country" id="donor-country" placeholder="Deutschland">
                    </div>
                </div>
            </div>
            <!-- Donor extra info end -->

            <div class="buttons row">
                <div class="col-sm-6 col-sm-push-3">
                    <button type="submit" class="btn btn-success btn-lg confirm" id="donation-submit">Bestätigen »</button>
                </div>
                <div class="col-sm-3 col-sm-pull-6">
                    <button type="button" class="btn btn-link unconfirm" id="donation-go-back">« Zurück</button>
                </div>
            </div>
        </div>




        <!-- Confirmation -->
        <div class="item<?= $confirmationCssClass ?>" id="donation-confirmation">
            <div class="alert alert-success flexible">
                <div class="response-icon noflex">
                    <img src="<?= plugins_url('images/success.png', __FILE__) ?>" alt="Success" width="18" height="18">
                </div>
                <div class"response-text">
                    <strong>Herzlichen Dank für Ihre Spende!</strong>
                </div>
            </div>
            <div id="receiver-bank-details" style="display: none">
                <h2>Überweisungen aus Deutschland</h2>
                <div>
                    <p>
                        <strong>IBAN:</strong> DE40 5605 1790 0002 2222 22<br />
                        <strong>BIC/SWIFT:</strong> MALADE51SIM<br />
                        <strong>Konto-Nr:</strong> 2 222 222<br />
                        <strong>BLZ:</strong> 560 517 90<br />
                        <strong>Bank:</strong> Kreissparkasse Rhein-Hunsrück, Vor dem Tor 1, DE-55469 Simmern<br />
                        <strong>Empfänger:</strong> Giordano-Bruno-Stiftung, Auf Fasel 16, DE-55430 Oberwesel<br />
                        <strong>Vermerk:</strong> GBS Schweiz
                    </p>
                    <p>Die in Deutschland ansässige Giordano-Bruno-Stiftung (gbs) nimmt Spenden aus Deutschland ab EUR 100.- für Sentience Politics entgegen und leitet sie direkt weiter in die Schweiz. Spenden an die gbs können in Deutschland bei der Steuererklärung geltend gemacht werden.</p>
                    <p>Die Spendenbescheinigung wird von gbs in Deutschland ausgestellt und per Briefpost versandt. Bitte geben Sie in den Überweisungsdetails Ihre Postanschrift an.</p>
                    <p>Nehmen Sie bei Fragen gerne mit uns <a href="/kontakt">Kontakt</a> auf.
                </div>
                <h2>Überweisungen aus der Schweiz und aus anderen Ländern</h2>
                <div>
                    <p>
                        <strong>Begünstigter:</strong> Effective Altruism Foundation, Efringerstrasse 25, CH-4057 Basel, Switzerland<br />
                        <strong>IBAN CHF:</strong> CH67 0023 3233 1775 4501 N<br />
                        <strong>IBAN EUR:</strong> CH20 0023 3233 1775 4560 D<br />
                        <strong>IBAN USD:</strong> CH79 0023 3233 1775 4561 F<br />
                        <strong>IBAN GBP:</strong> CH08 0023 3233 1775 4562 T<br />
                        <strong>BIC/SWIFT:</strong> UBSWCHZH80A<br />
                        <strong>Bank:</strong> UBS Switzerland AG, Aeschenvorstadt 1, CH-4051 Basel, Switzerland
                    </p>
                    <p>Die Stiftung für Effektiven Altruismus ist im Kanton Basel-Stadt und somit schweizweit als allgemeinnützige Organisation anerkannt. Spenden an die EAS können gemäß den kantonalen Regelungen bei den Steuererklärung geltend gemacht werden.</p>
                    <p>Sie erhalten Ihre Steuerbescheinigung per E-Mail. Falls sie eine postalische Bestätigung benötigen, teilen Sie uns dies und Ihre Anschrift bitte anhand des untenstehenden Formulars mit.</p>
                    <p>Nehmen Sie bitte <a href="/kontakt">Kontakt</a> mit uns auf, falls Sie steuerbefreit aus anderen Ländern an die EAS spenden möchten.
                </div>
            </div>
        </div>

      </div>
    </div>
  </div>

</form>

<!-- PayPal Adaptive payment form -->
<form action="<?= $GLOBALS['paypalPaymentEndpoint'] ?>" target="PPDGFrame" class="standard hidden">
    <input type="image" id="submitBtn" value="Pay with PayPal" src="https://www.paypalobjects.com/en_US/i/btn/btn_paynowCC_LG.gif">
    <input id="type" type="hidden" name="expType" value="light">
    <input id="paykey" type="hidden" name="paykey" value="">
</form>
<script type="text/javascript" charset="utf-8">
    var embeddedPPFlow = new PAYPAL.apps.DGFlow({trigger: 'submitBtn'});
</script>

<div id="drawer">Bitte alle obligatorischen Felder korrekt ausfüllen.</div>

</div>

<?php
  $content = ob_get_clean();
  return $content;
}














