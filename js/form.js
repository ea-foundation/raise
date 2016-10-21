/**
  * Settings
  */
var easFormName      = easDonationConfig.formName;
var easMode          = easDonationConfig.mode;
var userCountry      = easDonationConfig.userCountry;
var selectedCurrency = easDonationConfig.selectedCurrency;
var currencies       = wordpress_vars.amount_patterns;
var stripeHandlers   = null;
var buttonFinalText  = wordpress_vars.donate_button_text + ' »';
var totalItems       = 0;
var taxReceiptNeeded = false;
var slideTransitionInAction = false;
var otherAmountPlaceholder  = null;
var currentStripeKey = '';
var frequency        = 'once';
var monthlySupport   = ['payment-creditcard'];




// Preload Stripe image
var stripeImage = new Image();
stripeImage.src = wordpress_vars.plugin_path + 'images/logo.png';



/**
  * Form setup
  */
jQuery(document).ready(function($) {
    /**
     * Stripe setup
     */
    loadStripeHandler();


    // Country combobox setup
    $('.combobox', '#wizard').combobox({
        matcher: function (item) {
            return item.toLowerCase().indexOf(this.query.toLowerCase()) == 0;
        },
        appendId: '-auto'
    });



    totalItems = $('#wizard .item').length;
   
    // Some variables that we need
    var drawer = $("#drawer");

    // Page count
    $('button.unconfirm').click(function(event) {
        if (slideTransitionInAction) {
            return false;
        }

        var currentItem = $('#wizard div.active').index();

        if (currentItem  < 1) {
            return false;
        }

        // Go to previous step
        carouselPrev();
    });

    // Prevent interaction during carousel slide
    $('#donation-carousel').on('slide.bs.carousel', function () {
        slideTransitionInAction = true;
    });
    $('#donation-carousel').on('slid.bs.carousel', function () {
        slideTransitionInAction = false;
    });

    // Prevent non-ajax form submission
    $("#donationForm").submit(function(event) {
        event.preventDefault();
    });

    // Validation logic is done inside the onBeforeSeek callback
    $('button.confirm').click(function(event) {
        event.preventDefault();
        if (slideTransitionInAction) {
            return false;
        }

        var currentItem = $('div.active', '#wizard').index() + 1;

        // Check contents
        if (currentItem <= totalItems) {
            // Get all fields inside the page
            var inputs = $('div.item.active :input', '#wizard');

            // Remove errors
            inputs.siblings('span.eas-error').remove();
            inputs.parent().parent().removeClass('has-error');

            // Get all required fields inside the page
            var reqInputs = $('div.item.active .required :input', '#wizard');
            // ... which are empty or invalid
            var empty = reqInputs.filter(function() {
                return $(this).val().replace(/\s*/g, '') == '';
            });

            // Check invalid input
            var invalid = inputs.filter(function() {
                return ($(this).attr('id') == 'amount-other' && $(this).val() && isNaN($(this).val())) ||
                       ($(this).attr('type') == 'email' && !isValidEmail($(this).val().trim()))
            });

            // Unchecked radio groups
            var emptyRadios = $('div.item.active .required:has(:radio):not(:has(:radio:checked))', '#wizard');

            // If there are empty fields, then
            if (empty.length + invalid.length + emptyRadios.length) {
                // slide down the drawer
                drawer.slideDown(function()  {     
                    // Colored flash effect
                    drawer.css("backgroundColor", "#fff");
                    //setTimeout(function() { drawer.css("backgroundColor", "#fff"); }, 1000);
                });

                // Add a error CSS for empty and invalid fields
                empty = $.unique($.merge(empty, invalid));
                empty.each(function(index) {
                    // Don't add X icon to combobox. It looks bad
                    if ($(this).attr('type') != 'hidden' && $(this).attr('id') != 'donor-country-auto') {
                        if ($(this).attr('id') != 'donor-country') {
                            $(this).parent().append('<span class="eas-error glyphicon glyphicon-remove form-control-feedback" aria-hidden="true"></span>');
                        }
                        $(this).parent().parent().addClass('has-error');
                        $(this).attr('aria-describedby', 'inputError2Status' + index)
                        $(this).parent().append('<span id="inputError2Status' + index + '" class="eas-error sr-only">(error)</span>');
                    }
                });


                // cancel seeking of the scrollable by returning false
                return false;
            // everything is good
            } else {
                // hide the drawer
                drawer.slideUp();
            }
        }

        // post data and quit on last page
        if (currentItem >= (totalItems - 1)) {
            switch ($('input[name=payment]:checked', '#wizard').attr('id')) {
                case 'payment-creditcard':
                    handleStripeDonation();
                    break;
                case 'payment-paypal':
                    handlePaypalDonation();
                    break;
                case 'payment-banktransfer':
                    handleBankTransferDonation();
                    break;
                default:
                    //$('#donationForm').ajaxSubmit();
            }

            // Done, wait for callback functions
            return false;
        }

        if (currentItem == (totalItems - 2)) {
            // on penultimate page replace "confirm" with "donate X CHF"
            var foo = setTimeout(function() { showLastItem(currentItem) }, 200);
        } else {
            // Go to next slide
            carouselNext();
        }
    });

    // Click on other amount
    $('input#amount-other').focus(function() {
        if ($(this).hasClass("active")) {
            return;
        }
        $('ul#amounts label').removeClass("active");
        $('ul#amounts input:radio').prop('checked', false);
        if (otherAmountPlaceholder == null) {
            otherAmountPlaceholder = $(this).attr('placeholder');
        }
        $(this).attr('placeholder', '');
        $(this).addClass("active").parent().addClass('required');
        $(this).siblings('span.input-group-addon').addClass('active');
        $(this).siblings('label').addClass('active');
        enableConfirmButton(0);
    }).blur(function() {
        $(this).attr('placeholder', otherAmountPlaceholder);
    }).siblings('span.input-group-addon').click(function() {
        $(this).siblings('input').focus();
    });

    // Click on frequency labels
    $('ul#frequency label').click(function() {
        // Make new label active
        $(this).parent().parent().find('label').removeClass('active');
        $(this).addClass('active');
        frequency = $(this).siblings('input').val();

        // Hide payment options that do not support monthly
        var paymentOptions = $('#payment-method-providers label');
        if (frequency == 'monthly') {
            var checked = false;
            paymentOptions.each(function(index) {
                if ($.inArray($(this).attr('for'), monthlySupport) == -1) {
                    $(this).css('display', 'none');
                    $(this).find('input').prop('checked', false);
                } else {
                    // Check first possible option
                    if (!checked) {
                        checked = true;
                        $(this).find('input').prop('checked', true);
                    }
                }
            });
        } else {
            // Make all options visible again
            paymentOptions.each(function(index) {
                $(this).css('display', 'inline-block');
            });
        }
    });

    // Click on amount label (buttons)
    $('ul#amounts label').click(function() {
        if (slideTransitionInAction) {
            return false;
        }

        // See if already checked
        if ($('input[id=' + $(this).attr('for') +']', '#wizard').prop('checked')) {
            return false;
        }

        // Check if element has already been selected
        var firstSelection = $('ul#amounts label.active').length == 0;

        // Remove active css class from all items
        $('ul#amounts label').removeClass("active");

        var otherInput = $('input#amount-other');
        otherInput.siblings('span.eas-error').remove();
        otherInput
            .val('')
            .removeClass("active")
            .siblings('span.input-group-addon').removeClass('active')
            .parent().removeClass("required")
            .parent().removeClass('has-error')
        $(this).addClass("active");

        // Automatically go to next slide
        if (firstSelection) {
            enableConfirmButton(0);
            $('button.confirm:first').click();
        }
    });

    // Currency stuff
    $('#donation-currency input[name=currency]').change(function() {
        var selectedCurrencyInput = $(this).filter(':checked');
        selectedCurrency          = selectedCurrencyInput.val();

        // Remove old currency
        $('.cur', '#wizard').text('');
        
        // Update and close dropdown
        $('#selected-currency-flag')
            .removeClass()
            .addClass(selectedCurrencyInput.siblings('img').prop('class'))
            .prop('alt', selectedCurrencyInput.siblings('img').prop('alt'));
        $('#selected-currency').text(selectedCurrency);
        $(this).parent().parent().parent().parent().removeClass('open');

        // Set new currency on buttons and on custom input field
        var currencyString = currencies[easFormName][selectedCurrency];
        $('ul#amounts>li>label').text(
            function(i, val) {
                return currencyString.replace('%amount%', $(this).prev('input').attr('value')); 
            }
        );
        $('ul#amounts span.input-group-addon').text($.trim(currencyString.replace('%amount%', '')));

        // Reload Stripe handler
        loadStripeHandler();
    });

    // Purpose dropdown stuff
    $('#donation-purpose input[type=radio]').change(function() {
        $('#selected-purpose').text($(this).parent().contents().filter(function() {
            return this.nodeType == 3;
        }).text());
    });

    // Country dropdown stuff
    $('#donor-extra-info select#donor-country').change(function() {
        // Reload Stripe handler
        var option      = $(this).find('option:selected');
        var countryCode = option.val();

        if (!!countryCode) {
            userCountry = countryCode;

            // Make sure it's displyed correctly (autocomplete may mess with it)
            $('input#donor-country-auto').val(option.text());
            $('#donor-extra-info input[name=country]').val(countryCode);

            // Reload stripe handlers
            loadStripeHandler();
        }
    });

    // Show div with payment details
    var paymentPanels = $('div#payment-method-item div.radio > div');
    $('div#payment-method-item input[name=payment]').change(function() {
        // slide all panels up
        paymentPanels.slideUp();
        // ... except the one that was clicked
        $(this).parent().next().slideDown();

        // remove required class from all panels
        paymentPanels.children('div').removeClass('required');
        // ... except the one taht was clicked
        $(this).parent().next().children('div').addClass('required');
    });

    // Tax receipt toggle
    $('input#tax-receipt').change(function() {
        taxReceiptNeeded = $(this).is(':checked');
        // Toggle donor form display and required class
        if ($('div#donor-extra-info').css('display') == 'none') {
            $('div#donor-extra-info').slideDown();
            $('div#donor-extra-info div.optionally-required').addClass('required');
        } else {
            $('div#donor-extra-info').slideUp();
            $('div#donor-extra-info div.optionally-required').removeClass('required');
        }
        // Reload stripe settings
        loadStripeHandler();
    });
}); // End jQuery(document).ready()




/**
 * Auxiliary functions
 */

function isValidEmail(email) {
    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,10})+$/;
    return regex.test(email);
}

function enableConfirmButton(n)
{
    jQuery('button.confirm:eq(' + n + ')').removeAttr('disabled');
}

function getLastButtonText(formName)
{
    var amount         = getDonationAmount();
    var currencyCode   = getDonationCurrencyIsoCode();
    var currencyAmount = currencies[formName][currencyCode].replace('%amount%', amount);
    return buttonFinalText.replace('%currency-amount%', currencyAmount);
}

function showLastItem(currentItem)
{
    // Change text of last confirm button
    jQuery('button.confirm:last', '#wizard').text(getLastButtonText(easFormName));

    // Go to next slide
    carouselNext();
}

function getDonationAmount()
{
    var amount = jQuery('input[name=amount]:radio:checked', '#wizard').val();
    if (amount) {
        return amount.replace('.00', '');
    } else {
        amount = parseInt(jQuery('input#amount-other', '#wizard').val() * 100);
        if (!isNaN(amount) && amount >= 100) {
            amount = amount / 100;
            return (amount % 1 == 0) ? amount : amount.toFixed(2);
        } else {
            return 1; // default donation
        }
    }
}

function getDonationCurrencyIsoCode()
{
    //return jQuery('input[name=currency]:checked').val();
    return selectedCurrency;
}

/**
 * Handle Stripe donation
 */
function handleStripeDonation()
{
    stripeHandler.open({
        name: wordpress_vars.organization,
        description: wordpress_vars.donation,
        amount: getDonationAmount() * 100,
        currency: getDonationCurrencyIsoCode(),
        email: getDonorInfo('email')
    });
}

/**
 * Handle Paypal donation
 */
function handlePaypalDonation()
{
    // Show spinner right away
    showSpinnerOnLastButton();

    try {
        // Change form action (endpoint) and action input
        jQuery('form#donationForm').attr('action', wordpress_vars.ajax_endpoint);
        jQuery('form#donationForm input[name=action]').val('paypal_paykey');

        // Get pay key
        jQuery('form#donationForm').ajaxSubmit({
            success: function(responseText, statusText, xhr, form) {
                // Take the pay key and start the PayPal flow
                var response = JSON.parse(responseText);
                if (!('success' in response) || !response['success']) {
                    var message = 'error' in response ? response['error'] : responseText;
                    throw new Error(message);
                }

                // Insert pay key in PayPal form
                jQuery('input[id=paykey]').val(response['paykey']);
                
                // Open PayPal lightbox
                jQuery('input[id=submitBtn]').click();
            },
            error: function(responseText) {
                // Should only happen on internal server error
                throw new Error(responseText);
            }
        });

        // Disable confirm button, email and checkboxes
        lockLastStep(true);
    } catch (ex) {
        alert(ex.message);
    }
}

function handleBankTransferDonation()
{
    // Show spinner
    showSpinnerOnLastButton();

    // Send form
    jQuery('form#donationForm').ajaxSubmit({
        success: function(responseText, statusText, xhr, form) {
            try {
                var response = JSON.parse(responseText);
                if (!('success' in response) || !response['success']) {
                    var message = 'error' in response ? response['error'] : responseText;
                    throw new Error(message);
                }

                // Everything worked! Display short code content on confirmation page
                // Change glyphicon from "spinner" to "OK" and go to confirmation page
                showConfirmation('banktransfer');
            } catch (ex) {
                // Something went wrong, show on confirmation page
                alert(ex.message);

                // Enable buttons
                lockLastStep(false);
            }
        }
    });

    // Disable submit button, back button, and payment options
    lockLastStep(true);
}

function showSpinnerOnLastButton()
{
    jQuery('button.confirm:last', '#wizard').html('<span class="glyphicon glyphicon-refresh glyphicon-refresh-animate" aria-hidden="true"></span>');
}

function lockLastStep(locked)
{
    jQuery('#donation-submit').prop('disabled', locked);
    jQuery('#donation-go-back').prop('disabled', locked);
    jQuery('div.donor-info input', '#payment-method-item').prop('disabled', locked);
    jQuery('div.donor-info button', '#payment-method-item').prop('disabled', locked);
    jQuery('div.radio input', '#payment-method-item').prop('disabled', locked);
    jQuery('div.checkbox input', '#payment-method-item').prop('disabled', locked);

    if (!locked) {
        // Restore submit button
        jQuery('button.confirm:last', '#wizard').html(getLastButtonText(easFormName));
    }
}

function showConfirmation(paymentProvider)
{
    // Hide all payment provider related divs on confirmation page except the ones from paymentProvider
    jQuery('#payment-method-providers input[name=payment]').each(function(index) {
        var provider = jQuery(this).attr('value').toLowerCase();
        if (paymentProvider != provider) {
            jQuery('#shortcode-content > div.eas-' + provider).hide();
        }
    });

    // Hide spinner
    jQuery('button.confirm:last', '#wizard').html('<span class="glyphicon glyphicon-ok" aria-hidden="true"></span>');
    
    // Move to confirmation page after 1 second
    setTimeout(carouselNext, 1000);
}

function loadStripeHandler()
{
    //console.log('Loading Stripe handler...');
    // Lock form
    lockLastStep(true);

    // Get best matching key
    var stripeSettings = wordpress_vars.stripe_public_keys[easFormName];
    var newStripeKey   = '';

    // Check all possible settings
    var hasCountrySetting  = checkNestedArray(stripeSettings, userCountry.toLowerCase(), easMode);
    var hasCurrencySetting = checkNestedArray(stripeSettings, selectedCurrency.toLowerCase(), easMode);
    var hasDefaultSetting  = checkNestedArray(stripeSettings, 'default', easMode);
    
    // Check if there are settings for a country where the chosen currency is used.
    // This is only relevant if the donor does not need a donation receipt (always related 
    // to specific country) and if there are no currency specific settings
    var hasCountryOfCurrencySetting = false;
    var countryOfCurrency           = '';
    if (!taxReceiptNeeded && !hasCurrencySetting) {
        var countries = getCountriesByCurrency(selectedCurrency);
        for (var i = 0; i < countries.length; i++) {
            if (checkNestedArray(stripeSettings, countries[i].toLowerCase(), easMode)) {
                hasCountryOfCurrencySetting = true;
                countryOfCurrency = countries[i];
                break;
            }
        }
    }
    
    if (taxReceiptNeeded && hasCountrySetting) {
        // Use country specific key
        //console.log('Special settings for country ' + userCountry);
        newStripeKey = stripeSettings[userCountry.toLowerCase()][easMode];
    } else if (hasCurrencySetting) {
        // Use currency specific key
        //console.log('Special settings for currency ' + selectedCurrency);
        newStripeKey = stripeSettings[selectedCurrency.toLowerCase()][easMode];
    } else if (hasCountryOfCurrencySetting) {
        // Use key of a country where the chosen currency is used
        //console.log('Special settings for currency country ' + countryOfCurrency);
        newStripeKey = stripeSettings[countryOfCurrency.toLowerCase()][easMode];
    } else if (hasDefaultSetting) {
        // Use default key
        //console.log('Default settings');
        newStripeKey = stripeSettings['default'][easMode];
    } else {
        throw new Error('No Stripe settings found');
    }

    // Check if the key changed
    if (currentStripeKey == newStripeKey) {
        //console.log('Same key. Done.');
        // Unlock form
        lockLastStep(false);
        return;
    }

    // Create new Stripe handler
    stripeHandler = StripeCheckout.configure({
        key: newStripeKey,
        image: wordpress_vars.plugin_path + 'images/logo.png',
        color: '#255A8E',
        locale: 'auto',
        token: function(token) {
            //console.log("my object: %o", token);
            var tokenInput = jQuery('<input type="hidden" name="stripeToken">').val(token.id);
            var keyInput   = jQuery('<input type="hidden" name="stripePublicKey">').val(newStripeKey);

            // Show spinner
            showSpinnerOnLastButton();

            // Send form
            jQuery('form#donationForm').append(tokenInput).append(keyInput).ajaxSubmit({
                success: function(responseText, statusText, xhr, form) {
                    try {
                        var response = JSON.parse(responseText);
                        if (!('success' in response) || !response['success']) {
                            var message = 'error' in response ? response['error'] : responseText;
                            throw new Error(message);
                        }

                        // Everything worked! Change glyphicon from "spinner" to "OK" and go to confirmation page
                        showConfirmation('stripe');
                    } catch (ex) {
                        // Something went wrong, show on confirmation page
                        alert(ex.message);

                        // Enable buttons
                        lockLastStep(false);
                    }
                }
            });

            // Disable submit button, back button, and payment options
            lockLastStep(true);

            return false;
        }
    });

    // Update currentStripeKey
    currentStripeKey = newStripeKey;

    // Unlock last step
    lockLastStep(false);

    //console.log('Done');
}

function carouselNext()
{
    var currentItem = jQuery('#wizard div.active').index() + 1;

    if (currentItem  > totalItems) {
        return false;
    }

    // Move carousel
    jQuery('#donation-carousel').carousel('next');
    
    // Update status bar
    var listItems = jQuery("#status li");
    listItems.removeClass("active completed");
    listItems.filter(function(index) { return index < currentItem }).addClass("completed");
    listItems.eq(currentItem).addClass("active");
}


function carouselPrev()
{
    var currentItem = jQuery('#wizard div.active').index() - 1;

    if (currentItem  < 0) {
        return false;
    }

    // Move carousel
    jQuery('#donation-carousel').carousel('prev');
    
    // Update status bar
    var listItems = jQuery("#status li");
    listItems.removeClass("active completed");
    listItems.filter(function(index) { return index < currentItem }).addClass("completed");
    listItems.eq(currentItem).addClass("active");
}

function getDonorInfo(name)
{
    return jQuery('input#donor-' + name).val();
}

/**
 * Check if all nested array keys exist. Corresponds to PHP isset()
 */
function checkNestedArray(obj /*, level1, level2, ... levelN*/) {
    var args = Array.prototype.slice.call(arguments, 1);
    
    for (var i = 0; i < args.length; i++) {
        if (!obj || !obj.hasOwnProperty(args[i])) {
            return false;
        }
        obj = obj[args[i]];
    }

    return true;
}

/**
 * Get array with country codes where currency is used
 *
 * E.g. "CHF" returns ["CH", "LI"]
 */
function getCountriesByCurrency(currency)
{
    var mapping = wordpress_vars.currency2country;

    if (currency in mapping) {
        return mapping[currency];
    } else {
        return [];
    }
}













