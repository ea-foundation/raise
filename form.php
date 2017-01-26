<?php if (!defined('ABSPATH')) exit;

/**
 * Shortcode for donation form
 *
 * @param array $atts Valid keys are name (form name) and live (mode)
 * @param string $content Page contents for donation confirmation step
 * @return string HTML form contents
 */
function donationForm($atts, $content = null)
{
    // Enqueue previously registered scripts and styles (to prevent them loading on every page load)
    wp_enqueue_script('donation-plugin-bootstrapjs');
    wp_enqueue_script('donation-plugin-jqueryformjs');
    wp_enqueue_script('donation-plugin-stripe');
    wp_enqueue_script('donation-plugin-paypal');
    wp_enqueue_script('donation-plugin-form');
    wp_enqueue_script('donation-combobox');

    // Extract shortcode attributes (name becomes $name, etc.)
    extract(shortcode_atts(array(
        'name' => 'default',
        'live' => 'true',
    ), $atts));
    // Make sure we have a boolean
    $live = ($live == 'false' || $live == 'no' || $live == '0') ? false : true;
    $mode = !$live ? 'sandbox' : 'live';

    // Load settings
    $easForms = $GLOBALS['easForms'];
    if (empty($easForms[$name])) {
        echo 'No settings found for form ' . $name . '. See Settings > Donation Settings';
        return;
    }
    $easSettings = $easForms[$name];

    // Get language
    $segments = explode('_', get_locale(), 2);
    $language = reset($segments);

    // Get user country using freegeoip.net
    if (isset($easSettings["amount.currency"])) {
        $supportedCurrencies = array();
        foreach ($easSettings["amount.currency"] as $supportedCurrency => $currencySetting) {
            $supportedCurrencies[strtoupper($supportedCurrency)] = $currencySetting;
        }
    } else {
        $supportedCurrencies = array();
    }
    $userCountry                = getUserCountry();
    $userCountryCode            = $userCountry['code'];
    $userCurrency               = getUserCurrency($userCountryCode);
    $supportedCurrencyCodes     = array_keys($supportedCurrencies);
    $preselectedCurrency        = $userCurrency && in_array($userCurrency, $supportedCurrencyCodes) ? $userCurrency : reset($supportedCurrencyCodes);
    $preselectedCurrencyFlag    = $preselectedCurrency && isset($supportedCurrencies[$preselectedCurrency]['country_flag']) ? $supportedCurrencies[$preselectedCurrency]['country_flag'] : '';
    $preselectedCurrencyPattern = $preselectedCurrency && isset($supportedCurrencies[$preselectedCurrency]['pattern']) ? $supportedCurrencies[$preselectedCurrency]['pattern'] : '';

    // Handle redirection case after successful payment
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

<!-- Donation form -->
<form action="<?php echo admin_url('admin-ajax.php') ?>" method="post" id="donationForm" class="form-horizontal">

<script>
    var easDonationConfig = {
        formName:         "<?php echo $name ?>",
        mode:             "<?php echo $mode ?>",
        userCountry:      "<?php echo $userCountryCode ?>",
        selectedCurrency: "<?php echo $preselectedCurrency ?>"
    }
</script>
<input type="hidden" name="action" value="donate"> <!-- ajax key -->
<input type="hidden" name="form" value="<?php echo $name ?>" id="eas-form-name">
<input type="hidden" name="mode" value="<?php echo $live ? 'live' : 'sandbox' ?>" id="eas-form-mode">
<input type="hidden" name="language" value="<?php echo $language ?>" id="eas-form-language">

<!-- Scrollable root element -->
<div id="wizard">
    <!-- Progress bar -->
    <div id="progress" class="row">
        <ol>
            <li class="col-xs-4<?php echo $checkoutCssClass ?>"><span>1</span> <?php _e('Amount', 'eas-donation-processor') ?></li>
            <li class="col-xs-4"><span>2</span> <?php _e('Payment', 'eas-donation-processor') ?></li>
            <li class="col-xs-4<?php echo $confirmationCssClass ?>"><span>3</span> <?php _e('Finish', 'eas-donation-processor') ?></li>
        </ol>
    </div>

    <!-- Scrollable items -->
    <div id="donation-carousel" class="carousel slide" data-ride="carousel" data-interval="false" data-wrap="false" data-keyboard="false">
        <div class="carousel-inner" role="listbox">



            <!-- Amount -->
            <div class="item<?php echo $checkoutCssClass ?>" id="amount-item">
                <div class="sr-only">
                  <h3><?php _e('My Donation', 'eas-donation-processor') ?></h3>
                </div>
                <!-- Currency -->
                <div class="<?php echo count($supportedCurrencies) > 1 ? 'row' : 'hidden' ?>">
                    <div class="col-xs-12" id="donation-currency">
                        <div class="btn-group">
                            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                              <img src="<?php echo plugins_url('images/blank.gif', __FILE__) ?>" id="selected-currency-flag" class="flag flag-<?php echo $preselectedCurrencyFlag ?>" alt="<?php echo $preselectedCurrency ?>">
                              <span id="selected-currency"><?php echo $preselectedCurrency ?></span>
                              <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu">
                                <?php
                                    foreach ($supportedCurrencies as $currency => $currencySettings) {
                                        $flagCss  = isset($currencySettings['country_flag']) ? 'flag-' . $currencySettings['country_flag'] : '';
                                        $checked  = $currency == $preselectedCurrency ? 'checked' : '';
                                        echo '<li><label for="currency-' . strtolower($currency) . '"><input type="radio" id="currency-' . strtolower($currency) . '" name="currency" value="' . $currency . '" class="hidden" ' . $checked . '><img src="'. plugins_url("images/blank.gif", __FILE__) . '" class="flag ' . $flagCss . '" alt="' . $currency . '">' . $currency . '</label></li>';
                                    }
                                ?>
                            </ul>
                       </div>
                    </div>
                </div>

                <div class="row">
                    <ul id="frequency" class="col-xs-12">
                        <li>
                            <input type="radio" class="radio" name="frequency" value="once" id="frequency-once" checked>
                            <label for="frequency-once" class="active"><?php _e('Give once', 'eas-donation-processor') ?></label>
                        </li><li>
                            <input type="radio" class="radio" name="frequency" value="monthly" id="frequency-monthly">
                            <label for="frequency-monthly"><?php _e('Give monthly', 'eas-donation-processor') ?></label>
                        </li>
                    </ul>
                </div>

                <div class="row">
                    <ul id="amounts" class="radio">
                        <?php
                            // Once buttons
                            $cols          = min(12, get($easSettings["amount.columns"], 3));
                            $buttonColSpan = floor(12 / $cols);
                            $tabIndex      = 0;
                            if (!empty($easSettings["amount.button"]) && is_array($easSettings["amount.button"])) {
                                foreach ($easSettings["amount.button"] as $amount) {
                                    echo '<li class="col-xs-' . $buttonColSpan . ' amount-once">';
                                    echo '    <input type="radio" class="radio" name="amount" value="' . $amount . '" tabindex="' . ++$tabIndex . '" id="amount-once-' . $amount . '">';
                                    echo '    <label for="amount-once-' . $amount . '">' . str_replace('%amount%', $amount, $preselectedCurrencyPattern) . '</label>';
                                    echo '</li>';
                                }
                            }

                            // Monthly buttons (if present)
                            $tabIndexMonthly = $tabIndex;
                            if (!empty($easSettings["amount.button_monthly"]) && is_array($easSettings["amount.button_monthly"])) {
                                foreach ($easSettings["amount.button_monthly"] as $amount) {
                                    echo '<li class="col-xs-' . $buttonColSpan . ' amount-monthly hidden">';
                                    echo '    <input type="radio" class="radio" name="amount" value="' . $amount . '" tabindex="' . ++$tabIndexMonthly . '" id="amount-monthly-' . $amount . '" disabled>';
                                    echo '    <label for="amount-monthly-' . $amount . '">' . str_replace('%amount%', $amount, $preselectedCurrencyPattern) . '</label>';
                                    echo '</li>';
                                }
                            }
                        ?>

                        <?php if (get($easSettings["amount.custom"], true)): ?>

                            <li class="col-xs-<?php echo  12 - ($buttonColSpan * $tabIndex % 12) ?>">
                                <div class="input-group">
                                   <span class="input-group-addon"><?php echo trim(str_replace('%amount%', '', $preselectedCurrencyPattern)); ?></span>
                                    <input type="text" class="form-control input-lg text" name="amount_other" id="amount-other" placeholder="<?php _e('Other', 'eas-donation-processor') ?>" tabindex="<?php echo ++$tabIndexMonthly ?>">
                                    <label for="amount-other" class="sr-only"><?php _e('Other', 'eas-donation-processor') ?></label>
                                </div>
                            </li>

                        <?php endif; ?>
                    </ul>
                </div>
                <div class="buttons row">
                    <div class="col-sm-4 col-sm-offset-4">
                        <button type="button" class="btn btn-success btn-lg confirm" disabled="disabled"><?php _e('Next', 'eas-donation-processor') ?> »</button>
                    </div>
                </div>
            </div>








            <!-- Payment -->
            <div class="item" id="payment-method-item">
                <div class="sr-only">
                    <h3><?php _e('Choose a payment method', 'eas-donation-processor') ?></h3>
                </div>
                <div class="form-group payment-info" id="payment-method-providers">
                    <?php $checked = 'checked'; ?>
                    <?php if (!empty($easSettings["payment.provider.stripe.$mode.public_key"])): ?>
                        <label for="payment-creditcard" class="radio-inline">
                            <input type="radio" name="payment" value="Stripe" id="payment-creditcard" <?php echo $checked ?: ''; $checked = false; ?>>
                            <img src="<?php echo plugins_url('images/visa.png', __FILE__) ?>" alt="Visa" width="38" height="23">
                            <img src="<?php echo plugins_url('images/mastercard.png', __FILE__) ?>" alt="Mastercard" width="38" height="23">
                            <img src="<?php echo plugins_url('images/americanexpress.png', __FILE__) ?>" alt="American Express" width="38" height="23">
                        </label>
                    <?php endif; ?>

                    <?php if (!empty($easSettings["payment.provider.paypal.$mode.email_id"])): ?>
                        <label for="payment-paypal" class="radio-inline">
                            <input type="radio" name="payment" value="PayPal" id="payment-paypal" <?php echo $checked ?: ''; $checked = false; ?>>
                            <img src="<?php echo plugins_url('images/paypal.png', __FILE__) ?>" alt="Paypal" width="38" height="23">
                        </label>
                    <?php endif; ?>

                    <!-- <label for="payment-skrill">
                        <input type="radio" class="radio" name="payment" value="Skrill" id="payment-skrill">
                        <img src="<?php echo plugins_url('images/skrill.png', __FILE__) ?>" alt="Skrill" width="38" height="23">
                    </label> -->

                    <?php if (!empty($easSettings["payment.provider.gocardless.$mode.access_token"])): ?>
                        <label for="payment-directdebit" class="radio-inline">
                            <input type="radio" name="payment" value="GoCardless" id="payment-directdebit" <?php echo $checked ?: ''; $checked = false; ?>>
                            <a href="javascript:jQuery('#payment-directdebit').click()" data-toggle="tooltip" data-container="body" data-placement="top" title="<?php _e('Available for Eurozone, UK, and Sweden', 'eas-donation-processor') ?>" style="text-decoration: none; color: inherit;"><?php _e('Direct Debit', 'eas-donation-processor') ?></a>
                        </label>
                    <?php endif; ?>

                    <label for="payment-banktransfer" class="radio-inline">
                        <input type="radio" name="payment" value="Banktransfer" id="payment-banktransfer" <?php echo $checked ?: ''; $checked = false; ?>>
                        <?php _e('Bank transfer', 'eas-donation-processor') ?>
                    </label>
                </div>

                <!-- Name -->
                <div class="form-group required donor-info">
                    <label for="donor-name" class="col-sm-3 control-label"><?php _e('Name', 'eas-donation-processor') ?></label>
                    <div class="col-sm-9">
                        <input type="text" class="form-control text" name="name" id="donor-name" placeholder="">
                    </div>
                </div>

                <!-- Donate anonymously (matching campaigns) -->
                <?php if (!empty($easSettings['campaign']) && !empty($easSettings['payment.extra_fields.anonymous']) && $easSettings['payment.extra_fields.anonymous']): ?>
                <div class="form-group donor-info" style="margin-top: -17px">
                    <div class="col-sm-offset-3 col-sm-9">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="anonymous" id="donor-anonymous" value="1"> <?php _e('Don\'t publish my name', 'eas-donation-processor') ?>
                            </label>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Email -->
                <div class="form-group required donor-info">
                    <label for="donor-email" class="col-sm-3 control-label"><?php _e('Email', 'eas-donation-processor') ?></label>
                    <div class="col-sm-9">
                        <input type="email" class="form-control text" name="email" id="donor-email" placeholder="">
                    </div>
                </div>

                <!-- Anti-spam email (only used for bank transfer, reset on submit) -->
                <div id="email-confirm-group" class="form-group required donor-info">
                    <label for="donor-email-confirm" class="col-sm-3 control-label"><?php _e('Email', 'eas-donation-processor') ?></label>
                    <div class="col-sm-9">
                        <input type="email" class="form-control text" name="email-confirm" id="donor-email-confirm" value="">
                    </div>
                </div>

                <!-- Purpose -->
                <?php
                    if (!empty($easSettings['payment.purpose']) && is_array($easSettings['payment.purpose'])):
                        $firstItem = reset(array_values($easSettings['payment.purpose']));
                        if (is_array($firstItem)) {
                            $firstItem = getBestValue($firstItem);
                        }

                        // Don't print dropdown when only one purpose
                        if (count($easSettings['payment.purpose']) == 1):
                            $firstKey = reset(array_keys($easSettings['payment.purpose']));
                            echo '<input type="hidden" name="purpose" value="' . $firstKey . '">';
                        else:
                ?>
                    <div class="form-group donor-info" id="donation-purpose">
                        <label for="donor-purpose" class="col-sm-3 control-label"><?php _e('Purpose', 'eas-donation-processor') ?></label>
                        <div class="col-sm-9">
                            <button type="button" id="donor-purpose" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span id="selected-purpose"><?php echo $firstItem ?></span>
                                <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu scrollable-menu">
                                <?php
                                    $checked = 'checked';
                                    foreach ($easSettings['payment.purpose'] as $value => $labels) {
                                        // Check if there are language settings
                                        if (is_array($labels)) {
                                            $label = getBestValue($labels);
                                        } else {
                                            $label = $labels;
                                        }
                                        echo '<li><label for="purpose-' . strtolower($value) . '"><input type="radio" id="purpose-' . strtolower($value) . '" name="purpose" value="' . $value . '" class="hidden" '  . $checked . '>' . $label . '</label></li>';
                                        $checked = '';
                                    }
                                ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; endif; ?>

                <!-- Comment -->
                <?php if (!empty($easSettings['payment.extra_fields.comment']) && $easSettings['payment.extra_fields.comment']): ?>
                <div class="form-group donor-info">
                    <label for="donor-comment" class="col-sm-3 control-label"><?php _e('Public comment', 'eas-donation-processor') ?> (<?php _e('optional', 'eas-donation-processor') ?>)</label>
                    <div class="col-sm-9">
                        <textarea class="form-control" rows="3" name="comment" id="donor-comment"></textarea>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Mailing list -->
                <?php if (!empty($easSettings['webhook.mailing_list'])): ?>
                <div class="form-group donor-info">
                    <div class="col-sm-offset-3 col-sm-9">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="mailinglist" id="donor-mailinglist" value="1" checked> <?php _e('Subscribe me to monthly EA updates', 'eas-donation-processor') ?>
                            </label>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tax receipt -->
                <div class="form-group donor-info" <?php echo !empty($easSettings['webhook.mailing_list']) ? 'style="margin-top: -15px"' : ''; ?>>
                    <div class="col-sm-offset-3 col-sm-9">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="tax_receipt" id="tax-receipt" value="1"> <?php _e('I need a tax receipt for Germany, Switzerland, the Netherlands, or the United States', 'eas-donation-processor') ?>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Donor extra info start -->
                <div id="donor-extra-info">
                    <div class="form-group donor-info optionally-required">
                        <label for="donor-address" class="col-sm-3 control-label"><?php _e('Address', 'eas-donation-processor') ?></label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control text" name="address" id="donor-address" placeholder="">
                        </div>
                    </div>

                    <div class="form-group donor-info optionally-required">
                        <label for="donor-zip" class="col-sm-3 control-label"><?php _e('Zip code', 'eas-donation-processor') ?></label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control text" name="zip" id="donor-zip" placeholder="">
                        </div>
                    </div>

                    <div class="form-group donor-info optionally-required">
                        <label for="donor-city" class="col-sm-3 control-label"><?php _e('City', 'eas-donation-processor') ?></label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control text" name="city" id="donor-city" placeholder="">
                        </div>
                    </div>

                    <div class="form-group donor-info optionally-required">
                        <label for="donor-country" class="col-sm-3 control-label"><?php _e('Country', 'eas-donation-processor') ?></label>
                        <div class="col-sm-9">
                            <select class="combobox" name="country" id="donor-country">
                                <option></option>
                                <?php
                                    $countries = getSortedCountryList();
                                    foreach ($countries as $code => $country) {
                                        $checked = $userCountryCode == $code ? 'selected' : '';
                                        echo '<option value="' . $code . '" '  . $checked . '>' . $country[0] . '</option>';
                                    }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <!-- Donor extra info end -->

                <div class="buttons row">
                    <div class="col-sm-6 col-sm-push-3">
                        <button type="submit" class="btn btn-success btn-lg confirm" id="donation-submit"><?php _e('Next', 'eas-donation-processor') ?> »</button>
                    </div>
                    <div class="col-xs-4 col-sm-3 col-sm-pull-6">
                        <button type="button" class="btn btn-link unconfirm" id="donation-go-back">« <?php _e('Back', 'eas-donation-processor') ?></button>
                    </div>
                    <div class="col-xs-8 col-sm-12" id="secure-transaction">
                        <p class="text-success">
                            <span class="glyphicon glyphicon-lock" aria-hidden="true"></span><span class="glyphicon glyphicon-ok glyphicon-overlay" aria-hidden="true"></span> <?php _e('Secure', 'eas-donation-processor') ?>
                        </p>
                    </div>
                </div>
            </div>




            <!-- Confirmation -->
            <div class="item<?php echo $confirmationCssClass ?>" id="donation-confirmation">
                <div class="alert alert-success flexible">
                    <div class"response-text">
                        <span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span>
                        <strong><?php echo isset($easSettings["finish.success_message.$language"]) ? $easSettings["finish.success_message.$language"] : defaultOption($name, 'finish.success_message') ?></strong>
                    </div>
                </div>
                <div id="shortcode-content">
                    <?php echo !empty($content) ? do_shortcode($content) : '' ?>
                </div>
            </div>


        </div>
    </div>
</div>

</form>

<!-- PayPal Adaptive payment form -->
<form action="<?php echo $GLOBALS['paypalPaymentEndpoint'][$mode] ?>" target="PPDGFrame" class="standard hidden">
    <input type="image" id="submitBtn" value="Pay with PayPal" src="https://www.paypalobjects.com/en_US/i/btn/btn_paynowCC_LG.gif">
    <input id="type" type="hidden" name="expType" value="light">
    <input id="paykey" type="hidden" name="paykey" value="">
</form>
<?php
    if (!function_exists('wp_add_inline_script')) {
        require_once(ABSPATH . 'wp-includes/functions.wp-scripts.php');
    }

    wp_add_inline_script('donation-plugin-form', "var embeddedPPFlow = new PAYPAL.apps.DGFlow({trigger: 'submitBtn'});"); // append to scripts instead of inline because main JS is loaded at the end
?>

<!-- GoCardless modal -->
<div id="goCardlessModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <img src="<?php echo plugins_url('images/gocardless.png', __FILE__) ?>" alt="GoCardless" height="16">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="gc_popup_open hidden">
                    <p><?php _e("Please continue the donation in the secure window that you've already opened.", "eas-donation-processor") ?></p>
                    <button class="btn btn-primary" onclick="gcPopup.focus()">OK</button>
                </div>
                <div class="gc_popup_closed">
                    <button id="goCardlessPopupButton" class="btn btn-primary"><span class="glyphicon glyphicon-lock" style="margin-right: 5px" aria-hidden="true"></span><?php _e("Set up Direct Debit", "eas-donation-processor") ?></button>
                </div>
            </div>
            <!-- <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div> -->
        </div>
    </div>
</div>

<div id="drawer"><?php _e('Please fill out all required fields correctly.', 'eas-donation-processor') ?></div>

<?php
    return ob_get_clean();
}
