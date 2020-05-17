<?php if (!defined('ABSPATH')) exit;

/**
 * Shortcode for donation form
 *
 * @param array $atts Valid keys are name (form name) and live (mode)
 * @param string $content Page contents for donation confirmation step
 * @return string HTML form contents
 */
function raise_form($atts, $content = null)
{
    // Disable caching
    if (!defined('DONOTCACHEPAGE')) {
        define('DONOTCACHEPAGE', true);
    }

    // Extract shortcode attributes (name becomes $form, etc.)
    extract(shortcode_atts([
        'form' => '',        // $form
        'live' => 'true',    // $live
    ], $atts));

    // Make sure we have a boolean
    $live = ($live == 'false' || $live == 'no' || $live == '0') ? false : true;
    $mode = !$live ? 'sandbox' : 'live';

    try {
        $formSettings = raise_init_donation_form($form, $mode);
    } catch (\Exception $ex) {
?>
    <div class="alert alert-danger" role="alert">
        <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
        <span class="sr-only">Error:</span>
        <?= $ex->getMessage() ?>
    </div>
<?php
        return;
    }

    // Get user country using freegeoip.net
    $userCountry                   = raise_get_initial_country($formSettings);
    $userCountryCode               = raise_get($userCountry['code']);
    $userCurrency                  = raise_get_user_currency($userCountryCode);
    $currencies                    = raise_get($formSettings['amount']['currency'], array());
    $supportedCurrencyCodes        = array_map('strtoupper', array_keys($currencies));
    $preselectedCurrency           = $userCurrency && in_array($userCurrency, $supportedCurrencyCodes) ? $userCurrency : reset($supportedCurrencyCodes);
    $lcCurrency                    = strtolower($preselectedCurrency);
    $preselectedCurrencyFlag       = raise_get($currencies[$lcCurrency]['country_flag'], '');
    $preselectedCurrencyPattern    = raise_get($currencies[$lcCurrency]['pattern'], '');
    $preselectedCurrencyLowerBound = raise_get($currencies[$lcCurrency]['lower_bound'], 1);

    // Handle redirection case after successful payment
    if (isset($_GET['success']) && $_GET['success'] == 'true') {
        // Set status to payed to prevent replay attacks on our logs
        $checkoutCssClass     = '';
        $confirmationCssClass = ' active';
    } else {
        $checkoutCssClass     = ' active';
        $confirmationCssClass = '';
    }
    ob_start();
?>

<!-- Start Bootstrap scope -->
<div class="btstrp">

<?php if ($mode == 'sandbox'): ?>
<div class="progress">
  <div class="progress-bar progress-bar-warning progress-bar-striped" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
    <span><strong>sandbox</strong></span>
  </div>
</div>
<?php endif; ?>

<!-- Donation form -->
<form action="<?php echo admin_url('admin-ajax.php') ?>" method="post" id="donationForm" class="form-horizontal">

<script>
    var raiseDonationConfig = {
        mode:              "<?= $mode ?>",
        userCountry:       "<?= $userCountryCode ?>",
        selectedCurrency:  "<?= $preselectedCurrency ?>",
        countryCompulsory: <?= raise_get($formSettings['payment']['extra_fields']['country'], false) ? 1 : 0 ?>
    }
</script>
<input type="hidden" name="action" value="process_banktransfer"> <!-- ajax key -->
<input type="hidden" name="form" value="<?= $form ?>" id="raise-form-name">
<input type="hidden" name="mode" value="<?= $mode ?>" id="raise-form-mode">
<input type="hidden" name="post_donation_instructions" value="" id="raise-form-post-donation-instructions">
<input type="hidden" name="locale" value="<?= get_locale() ?>">
<input type="hidden" name="share_data_offered" id="share-data-offered" value="0">
<input type="hidden" name="tip_offered" id="tip-offered" value="0">
<input type="hidden" name="tip_amount" id="tip-amount" value="0">
<input type="hidden" name="tip_percentage" id="tip-percentage" value="0">

<!-- Scrollable root element -->
<div id="wizard">
    <!-- Progress bar -->
    <div id="progress" class="row">
        <ol>
            <li class="col-xs-4<?php echo $checkoutCssClass ?>"><span>1</span> <?php _e('Amount', 'raise') ?></li>
            <li class="col-xs-4"><span>2</span> <?php _e('Payment', 'raise') ?></li>
            <li class="col-xs-4<?php echo $confirmationCssClass ?>"><span>3</span> <?php _e('Finish', 'raise') ?></li>
        </ol>
    </div>

    <!-- Scrollable items -->
    <div id="donation-carousel" data-ride="carousel" data-interval="false" data-wrap="false" data-keyboard="false">

        <!-- Amount slide -->
        <div class="item<?php echo $checkoutCssClass ?>" id="amount-item">
            <div class="sr-only">
                <h3><?php _e('My Donation', 'raise') ?></h3>
            </div>
            <!-- Currency -->
            <div class="<?php echo count($currencies) > 1 ? 'row' : 'hidden' ?>">
                <div class="col-xs-12" id="donation-currency">
                    <div class="btn-group">
                        <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <img src="<?php echo plugins_url('assets/images/blank.gif', __FILE__) ?>" id="selected-currency-flag" class="flag flag-<?php echo $preselectedCurrencyFlag ?>" alt="<?php echo $preselectedCurrency ?>">
                            <span id="selected-currency"><?php echo $preselectedCurrency ?></span>
                            <span class="caret"></span>
                        </button>
                        <ul class="dropdown-menu">
                            <?php
                                foreach ($currencies as $lcCurrency => $currencySettings) {
                                    $currency = strtoupper($lcCurrency);
                                    $flagCss  = isset($currencySettings['country_flag']) ? 'flag-' . $currencySettings['country_flag'] : '';
                                    $checked  = $currency == $preselectedCurrency ? 'checked' : '';
                                    echo '<li><label for="currency-' . $lcCurrency . '"><input type="radio" id="currency-' . $lcCurrency . '" name="currency" value="' . $currency . '" class="hidden" ' . $checked . '><img src="'. plugins_url("assets/images/blank.gif", __FILE__) . '" class="flag ' . $flagCss . '" alt="' . $currency . '">' . $currency . '</label></li>';
                                }
                            ?>
                        </ul>
                    </div>
                </div>
            </div>

            <?php
                $enabledProviders = raise_enabled_payment_providers($formSettings, $mode);
                $monthlyDisplay   = raise_monthly_frequency_supported($enabledProviders) ? '' : 'hidden'; 
            ?>
            <div class="row <?= $monthlyDisplay ?>">
                <ul id="frequency" class="col-xs-12">
                    <li>
                        <input type="radio" class="radio" name="frequency" value="once" id="frequency-once" checked>
                        <label for="frequency-once" class="active"><?php _e('Give once', 'raise') ?></label>
                    </li><li>
                        <input type="radio" class="radio" name="frequency" value="monthly" id="frequency-monthly">
                        <label for="frequency-monthly"><?php _e('Give monthly', 'raise') ?></label>
                    </li>
                </ul>
            </div>

            <div class="row">
                <ul id="amounts" class="radio">
                    <?php
                        // One-time buttons
                        $cols          = min(12, raise_get($formSettings['amount']['columns'], 3));
                        $buttonColSpan = floor(12 / $cols);
                        $tabIndex      = 0;
                        $amounts       = raise_get($formSettings['amount']['button'], array());
                        foreach (array_filter($amounts) as $amount) {
                            echo '<li class="col-xs-' . $buttonColSpan . ' amount-once">';
                            echo '    <input type="radio" class="radio" name="amount" value="' . $amount . '" tabindex="' . ++$tabIndex . '" id="amount-once-' . $amount . '">';
                            echo '    <label for="amount-once-' . $amount . '">' . str_replace('%amount%', $amount, $preselectedCurrencyPattern) . '</label>';
                            echo '</li>';
                        }

                        // Monthly buttons (if present)
                        $tabIndexMonthly = $tabIndex;
                        $amountsMonthly  = raise_get($formSettings['amount']['button_monthly'], []);
                        foreach (array_filter($amountsMonthly) as $amount) {
                            echo '<li class="col-xs-' . $buttonColSpan . ' amount-monthly hidden">';
                            echo '    <input type="radio" class="radio" name="amount" value="' . $amount . '" tabindex="' . ++$tabIndexMonthly . '" id="amount-monthly-' . $amount . '" disabled>';
                            echo '    <label for="amount-monthly-' . $amount . '">' . str_replace('%amount%', $amount, $preselectedCurrencyPattern) . '</label>';
                            echo '</li>';
                        }
                    ?>

                    <?php if (raise_get($formSettings['amount']['custom'], true)): ?>
                        <li class="col-xs-<?php echo  12 - ($buttonColSpan * $tabIndex % 12) ?>">
                            <div class="input-group">
                                <span class="input-group-addon"><?php echo trim(str_replace('%amount%', '', $preselectedCurrencyPattern)); ?></span>
                                <input type="number" min="<?= $preselectedCurrencyLowerBound ?>" class="form-control input-lg text" name="amount_other" id="amount-other" placeholder="<?php _e('Other', 'raise') ?>" tabindex="<?php echo ++$tabIndexMonthly ?>">
                                <label for="amount-other" class="sr-only"><?php _e('Other', 'raise') ?></label>
                            </div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="buttons row">
                <div class="col-sm-4 col-sm-offset-4">
                    <button type="button" class="btn btn-lg confirm donation-continue" disabled="disabled"><?php _e('Next', 'raise') ?></button>
                </div>
            </div>
        </div>








        <!-- Payment slide -->
        <div class="item" id="payment-method-item">
            <div class="sr-only">
                <h3><?php _e('Choose a payment method', 'raise') ?></h3>
            </div>
            <div class="form-group payment-info" id="payment-providers">
                <?= raise_print_payment_providers($formSettings, $mode); ?>
            </div>

            <!-- Name -->
            <div class="form-group required donor-info">
                <label for="donor-name" class="col-sm-3 control-label"><?php _e('Name', 'raise') ?></label>
                <div class="col-sm-9">
                    <input type="text" class="form-control text" name="name" id="donor-name" placeholder="">
                </div>
            </div>

            <!-- Donate anonymously (for fundraisers only) -->
            <?php if (!empty($formSettings['fundraiser']) && raise_get($formSettings['payment']['extra_fields']['anonymous'], false)): ?>
            <div class="form-group donor-info" style="margin-top: -17px">
                <div class="col-sm-offset-3 col-sm-9">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="anonymous" id="donor-anonymous" value="1"> <?php _e('Don\'t publish my name', 'raise') ?>
                        </label>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Email -->
            <div class="form-group required donor-info">
                <label for="donor-email" class="col-sm-3 control-label"><?php _e('Email', 'raise') ?></label>
                <div class="col-sm-9">
                    <input type="email" class="form-control text" name="email" id="donor-email" placeholder="">
                </div>
            </div>

            <!-- Anti-spam email (only used for bank transfer, reset on submit) -->
            <div id="email-confirm-group" class="form-group required donor-info">
                <label for="donor-email-confirm" class="col-sm-3 control-label"><?php _e('Email', 'raise') ?></label>
                <div class="col-sm-9">
                    <input type="email" class="form-control text" name="email-confirm" id="donor-email-confirm" value="">
                </div>
            </div>

            <!-- Country (if necessary for tax deduction) -->
            <?php if (raise_get($formSettings['payment']['extra_fields']['country'], false)): ?>
            <div class="form-group required donor-info">
                <label for="donor-country" class="col-sm-3 control-label"><?php _e('Country', 'raise') ?></label>
                <div class="col-sm-9">
                    <select class="combobox form-control" name="country_code" id="donor-country">
                        <option></option>
                        <?php
                            $countries = raise_get_sorted_country_list();
                            foreach ($countries as $code => $country) {
                                $checked = $userCountryCode === $code ? 'selected' : '';
                                echo '<option value="' . $code . '" '  . $checked . '>' . $country[0] . '</option>';
                            }
                        ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <!-- Purpose -->
            <?php
                if ($purposes = raise_get($formSettings['payment']['purpose'])):
                    // Localize labels
                    $purposes = raise_monolinguify($purposes);

                    // Check if selected purpose is deeplinked
                    if (isset($_GET['purpose']) && isset($purposes[$_GET['purpose']])) {
                        $purposeButtonLabel = $purposes[$_GET['purpose']];
                        $checked            = $_GET['purpose'];
                    } elseif (isset($purposes[''])) {
                        // Label of empty item ("Choose your purpose"), not selectable
                        $purposeButtonLabel = $purposes[''];
                        $checked            = ''; // none
                    } else {
                        // Label of first option (selected by default)
                        $purposeButtonLabel = reset($purposes);
                        $checked            = key($purposes);
                    }

                    // Unset empty purpose value
                    unset($purposes['']);

                    // Don't print dropdown when only one purpose
                    if (count($purposes) == 1):
                        $purposeKeys = array_keys($purposes);
                        $firstKey    = reset($purposeKeys);
                        echo '<input type="radio" name="purpose" value="' . $firstKey . '" class="hidden" checked>';
                    else:
            ?>
                <div class="form-group required donor-info" id="donation-purpose">
                    <label for="donor-purpose" class="col-sm-3 control-label"><?php echo raise_get_localized_value(raise_get($formSettings['payment']['labels']['purpose'], __('Purpose', 'raise'))) ?></label>
                    <div class="col-sm-9">
                        <button type="button" id="donor-purpose" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span id="selected-purpose"><?php echo $purposeButtonLabel ?></span>
                            <span class="caret"></span>
                        </button>
                        <ul class="dropdown-menu scrollable-menu">
                            <?php
                                foreach ($purposes as $value => $label) {
                                    $attr = $value == $checked ? ' checked' : '';
                                    echo '<li><label for="purpose-' . strtolower($value) . '"><input type="radio" id="purpose-' . strtolower($value) . '" name="purpose" value="' . $value . '" class="hidden"'  . $attr . '><span>' . $label . '</span></label></li>';
                                }
                            ?>
                        </ul>
                    </div>
                </div>
            <?php endif; endif; ?>

            <!-- Share with charity -->
            <?php if (!empty($formSettings['payment']['form_elements']['share_data'])): ?>
                <div id="share-data-form-group" class="form-group donor-info" style="margin-top: -10px; display: none">
                    <div class="col-sm-offset-3 col-sm-9">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="share_data" id="share-data" value="1" class="precheckable" disabled>
                                <span id="share-data-text"><?php _e('Share my data with recipient charity', 'raise'); ?></span>
                            </label>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Comment -->
            <?php if (raise_get($formSettings['payment']['extra_fields']['comment'], false)): ?>
                <div class="form-group donor-info">
                    <label for="donor-comment" class="col-sm-3 control-label"><?php _e('Public comment', 'raise') ?> (<?php _e('optional', 'raise') ?>)</label>
                    <div class="col-sm-9">
                        <textarea class="form-control" rows="3" name="comment" id="donor-comment"></textarea>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Mailing list -->
            <?php if (!empty($formSettings['webhook']['mailing_list'][$mode])): ?>
                <div class="form-group donor-info" style="margin-top: -10px">
                    <div class="col-sm-offset-3 col-sm-9">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="mailinglist" id="donor-mailinglist" value="1">
                                <?php
                                    if (!empty($formSettings['payment']['form_elements']['mailing_list'])) {
                                        echo esc_html(raise_get_localized_value($formSettings['payment']['form_elements']['mailing_list']));
                                    } else {
                                        _e('Subscribe me to newsletter', 'raise');
                                    }
                                ?>
                            </label>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tipping -->
            <?php if (!empty($formSettings['payment']['form_elements']['tip'])): ?>
                <div id="tip-form-group" class="form-group donor-info" style="margin-top: -10px; display: none">
                    <div class="col-sm-offset-3 col-sm-9">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="tip" id="tip" value="1" class="precheckable">
                                <span id="tip-text"></span>
                            </label>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tax receipt -->
            <div class="form-group donor-info" style="margin-top: -10px">
                <div class="col-sm-offset-3 col-sm-9">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="tax_receipt" id="tax-receipt" value="1" class="precheckable" disabled>
                            <span id="tax-receipt-text"><?php _e('I need a tax receipt', 'raise'); ?></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Donor extra info start -->
            <div id="donor-extra-info">
                <div class="form-group donor-info optionally-required">
                    <label for="donor-address" class="col-sm-3 control-label"><?php _e('Address', 'raise') ?></label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control text" name="address" id="donor-address" placeholder="">
                    </div>
                </div>

                <div class="form-group donor-info optionally-required">
                    <label for="donor-zip" class="col-sm-3 control-label"><?php _e('Zip code', 'raise') ?></label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control text" name="zip" id="donor-zip" placeholder="" maxlength="10">
                    </div>
                </div>

                <div class="form-group donor-info optionally-required">
                    <label for="donor-city" class="col-sm-3 control-label"><?php _e('City', 'raise') ?></label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control text" name="city" id="donor-city" placeholder="">
                    </div>
                </div>

                <?php if (empty($formSettings['payment']['extra_fields']['country']) || !$formSettings['payment']['extra_fields']['country']): ?>
                <div class="form-group donor-info optionally-required">
                    <label for="donor-country" class="col-sm-3 control-label"><?php _e('Country', 'raise') ?></label>
                    <div class="col-sm-9">
                        <select class="combobox form-control" name="country" id="donor-country">
                            <option></option>
                            <?php
                                $countries = raise_get_sorted_country_list();
                                foreach ($countries as $code => $country) {
                                    $checked = $userCountryCode == $code ? 'selected' : '';
                                    echo '<option value="' . $code . '" '  . $checked . '>' . $country[0] . '</option>';
                                }
                            ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <!-- Donor extra info end -->

            <div class="buttons row">
                <div class="col-sm-6 col-sm-push-3">
                    <button type="submit" class="btn btn-lg confirm donation-continue" id="donation-submit"><?php _e('Next', 'raise') ?></button>
                </div>
                <div class="col-xs-4 col-sm-3 col-sm-pull-6" style="text-align: left">
                    <button type="button" class="btn btn-link unconfirm" id="donation-go-back"><?php _e('Back', 'raise') ?></button>
                </div>
                <div class="col-xs-8 col-sm-12" id="secure-transaction">
                    <span class="glyphicon glyphicon-lock" aria-hidden="true"></span><span class="glyphicon glyphicon-ok glyphicon-overlay" aria-hidden="true"></span> <?php _e('Secure', 'raise') ?>
                </div>
            </div>
            <?php if ($siteKey = raise_get($formSettings['payment']['recaptcha']['site_key'])): ?>
            <div class="row relative">
                <div id='recaptcha' class="g-recaptcha"
                    data-sitekey="<?= $siteKey ?>"
                    data-callback="sendBanktransferDonation"
                    data-size="invisible"
                    data-badge="inline"></div>
                <div class="g-recaptcha-overlay" title="reCAPTCHA"></div>
            </div>
            <script src="//www.google.com/recaptcha/api.js" async defer></script>
            <?php endif; ?>
        </div>




        <!-- Confirmation slide -->
        <div class="item<?php echo $confirmationCssClass ?>" id="donation-confirmation">
            <div class="alert alert-success">
                <div class="response-icon"></div>
                <div class="response-text">
                    <strong><span id="success-text"><?php echo esc_html(raise_get_localized_value($formSettings['finish']['success_message'])) ?></span></strong>
                </div>
            </div>
            <div id="shortcode-content">
                <?php echo !empty($content) ? do_shortcode($content) : '' ?>
            </div>
        </div>


    </div>
</div>

</form>

<?php 
    if (in_array('paypal', $enabledProviders)): ?>
    <!-- PayPal modal -->
    <div id="PayPalModal" class="modal raise-modal raise-popup-modal fade" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="PayPalPopupButton"></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (in_array('gocardless', $enabledProviders)): ?>
    <!-- GoCardless modal -->
    <div id="GoCardlessModal" class="modal raise-modal raise-popup-modal fade" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="pp gocardless"></div>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="raise_popup_open hidden">
                        <p><?php _e("Please continue the donation in the secure window that you've already opened.", "raise") ?></p>
                        <button class="btn btn-primary" onclick="raisePopup.focus()">OK</button>
                    </div>
                    <div class="raise_popup_closed">
                        <p id="GoCardlessNote" class="hidden"><strong><?php _e("Note: GoCardless requires a mandate of type “recurrent”, but we will only collect once.", "raise") ?></strong></p>
                        <button id="GoCardlessPopupButton" class="btn btn-primary"><span class="glyphicon glyphicon-lock" style="margin-right: 5px" aria-hidden="true"></span><?php _e("Set up Direct Debit", "raise") ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (in_array('bitpay', $enabledProviders)): ?>
    <!-- Bitpay modal -->
    <div id="BitPayModal" class="modal raise-modal raise-popup-modal fade" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="pp bitpay"></div>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="raise_popup_open hidden">
                        <p><?php _e("Please continue the donation in the secure window that you've already opened.", "raise") ?></p>
                        <button class="btn btn-primary" onclick="raisePopup.focus()">OK</button>
                    </div>
                    <div class="raise_popup_closed">
                        <button id="BitPayPopupButton" class="btn btn-primary"><span class="glyphicon glyphicon-lock" style="margin-right: 5px" aria-hidden="true"></span><?php _e("Pay by Bitcoin", "raise") ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (in_array('coinbase', $enabledProviders)): ?>
    <!-- Coinbase modal -->
    <div id="CoinbaseModal" class="modal raise-modal raise-popup-modal fade" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="pp coinbase"></div>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="raise_popup_open hidden">
                        <p><?php _e("Please continue the donation in the secure window that you've already opened.", "raise") ?></p>
                        <button class="btn btn-primary" onclick="raisePopup.focus()">OK</button>
                    </div>
                    <div class="raise_popup_closed">
                        <p class="coinbase_notice">
                            <?php _e("<strong>Important:</strong> Do not close the popup window until the transaction has been <strong>verified</strong>. This can take <strong>up to 10 minutes</strong>. Once the transaction is verified, click on <strong>Continue</strong> or wait until the popup is closed automatically.", 'raise'); ?>
                        </p>
                        <button id="CoinbasePopupButton" class="btn btn-primary"><span class="glyphicon glyphicon-lock" style="margin-right: 5px" aria-hidden="true"></span><?php _e("Donate with Crypto", "raise") ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (in_array('skrill', $enabledProviders)): ?>
    <!-- Skrill modal -->
    <div id="SkrillModal" class="modal raise-modal raise-iframe-modal fade" role="dialog" data-backdrop="static">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div id="drawer"></div>

</div>
<!-- End Bootstrap scope -->
<?php
    return ob_get_clean();
}
