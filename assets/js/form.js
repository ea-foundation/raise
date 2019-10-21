/**
 * Settings
 */
var carousel                    = null;
var raiseMode                   = raiseDonationConfig.mode;
var userCountry                 = raiseDonationConfig.userCountry;
var selectedCurrency            = raiseDonationConfig.selectedCurrency;
var countryCompulsory           = raiseDonationConfig.countryCompulsory;
var currencies                  = wordpress_vars.amount_patterns;
var currencyMinimums            = wordpress_vars.amount_minimums;
var stripeHandlers              = null;
var totalItems                  = 0;
var taxReceiptNeeded            = false;
var slideTransitionInAction     = false;
var otherAmountPlaceholder      = null;
var currentStripeKey            = '';
var frequency                   = 'once';
var monthlySupport              = wordpress_vars.monthly_support.map(function(val) { return 'payment-' + val });
var raisePopup                  = null;
var gcPollTimer                 = null;
var taxReceiptDisabled          = true;
var interactionEventDispatched  = false;
var checkoutEventDispatched     = false;
var checkboxPreCheck            = {};


// Preload Stripe image
var stripeImage = new Image();
stripeImage.src = wordpress_vars.logo;

// Define Object keys for old browsers
if (!Object.keys) {
    Object.keys = function (obj) {
        var keys = [],
            k;
        for (k in obj) {
            if (Object.prototype.hasOwnProperty.call(obj, k)) {
                keys.push(k);
            }
        }
        return keys;
    };
}

/**
 * Setup form when DOM ready
 */
jQuery(function($) {
    // Make carousel
    carousel = $('#donation-carousel');
    carousel.slick({
        adaptiveHeight: true,
        arrows: false,
        draggable: false,
        swipe: false,
        touchMove: false,
        infinite: false,
    });

    // Make sure cookies are enabled
    if (!navigator.cookieEnabled) {
        $('<div class="alert alert-danger" role="alert"><span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span><span class="sr-only">Error:</span> ' + wordpress_vars.labels.cookie_warning + '</div>')
            .insertBefore(".btstrp");
    }

    // Dispatch raise_loaded_donation_form event
    raiseTriggerFormLoadedEvent();

    // Reload payment providers
    reloadPaymentProviders();

    // Reload dropdowns (can be broken depending on theme)
    $('.dropdown-toggle').dropdown();

    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

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

        var currentItem = carousel.slick('slickCurrentSlide');

        if (currentItem  < 1) {
            return false;
        }

        // Go to previous step
        carouselPrev();
    });

    // Prevent interaction during carousel slide
    carousel.on('beforeChange', function () {
        slideTransitionInAction = true;
    });
    carousel.on('afterChange', function () {
        slideTransitionInAction = false;
    });

    // Prevent non-ajax form submission
    $("#donationForm").submit(function(event) {
        event.preventDefault();
    });

    // Unlock form when Raise popup modal is hidden
    $("div.raise-modal").on('hide.bs.modal', function () {
        // No need to unlock form if donation complete
        if (jQuery('button.confirm:last span.glyphicon-ok', '#wizard').length == 0) {
            lockLastStep(false);
        }

        // Close popup (if still open)
        if ($(this).hasClass('raise-popup-modal') && raisePopup && !raisePopup.closed) {
            raisePopup.close();
        }
    });

    // Lock form when Raise popup modal is shown and reset modal contents
    $("div.raise-modal").on('show.bs.modal', function () {
        // Reset modal
        if ($(this).hasClass('raise-popup-modal')) {
            $(this).find('.modal-body .raise_popup_closed').removeClass('hidden');
            $(this).find('.modal-body .raise_popup_open').addClass('hidden');
        }
    });

    // Validation logic is done inside the onBeforeSeek callback
    $('button.confirm').click(function(event) {
        event.preventDefault();

        if (slideTransitionInAction) {
            return false;
        }

        var currentItem = carousel.slick('slickCurrentSlide') + 1;

        // Check contents
        if (currentItem <= totalItems) {
            // Get all fields inside the page, except honey pot (#donor-email-confirm)
            var inputs = $('div.slick-active :input', '#wizard').not('#donor-email-confirm');

            // Remove errors
            inputs.siblings('span.raise-error').remove();
            inputs.parent().parent().removeClass('has-error');

            // Get all required fields inside the page, except honey pot (#donor-email-confirm)
            var reqInputs = $('div.slick-active .required :input:not(:radio):not(:button)', '#wizard').not('#donor-email-confirm');
            // ... which are empty or invalid
            var errors  = {};
            var empty   = reqInputs.filter(function() {
                return $(this).val().replace(/\s*/g, '') == '';
            });

            // Check invalid input
            var invalid = inputs.filter(function() {
                if ($(this).attr('id') == 'amount-other' && $(this).val() && $(this).val() < currencyMinimums[selectedCurrency][frequency]) {
                    errors['amount'] = selectedCurrency in wordpress_vars.error_messages.below_minimum_amount_custom
                      ? wordpress_vars.error_messages.below_minimum_amount_custom[selectedCurrency]
                      : wordpress_vars.error_messages.below_minimum_amount;
                    return true;
                }

                if ($(this).attr('id') == 'donor-email' && !isValidEmail($(this).val().trim())) {
                    errors['donor-email'] = wordpress_vars.error_messages.invalid_email;
                    return true;
                }

                return false;
            });

            // Check amount < minimum from misconfiguration
            if (!$('#amount-other', '#wizard').val()) {
                var amount = getDonationAmount();
                if (amount < currencyMinimums[selectedCurrency][frequency]) {
                    errors['amount'] = selectedCurrency in wordpress_vars.error_messages.below_minimum_amount_custom
                      ? wordpress_vars.error_messages.below_minimum_amount_custom[selectedCurrency]
                      : wordpress_vars.error_messages.below_minimum_amount;
                    invalid.push('amount');
                }
            }

            // Unchecked radio groups (bootstrap drop downs). Add button instead.
            var emptyRadios = $('div.slick-active .required:has(:radio):not(:has(:radio:checked))', '#wizard');
            if (emptyRadios.find('button').length) {
                empty = $.merge(empty, emptyRadios.find('button'));
            }

            // If there are empty fields, then
            if (empty.length + invalid.length) {
                // Slide down the drawer
                drawer
                    .text(getErrorMessage(errors))
                    .slideDown();

                // Add a error CSS for empty and invalid fields
                empty = $.unique($.merge(empty, invalid));
                empty.each(function(index) {
                    // Don't add X icon to combobox. It looks bad
                    if ($(this).attr('type') != 'hidden' && $(this).attr('id') != 'donor-country-auto') {
                        if ($(this).attr('id') != 'donor-country') {
                            $(this).parent().append('<span class="raise-error glyphicon glyphicon-remove form-control-feedback" aria-hidden="true"></span>');
                        }
                        $(this).parent().parent().addClass('has-error');
                        $(this).attr('aria-describedby', 'inputError2Status' + index)
                        $(this).parent().append('<span id="inputError2Status' + index + '" class="raise-error sr-only">(error)</span>');
                    }
                });

                // Cancel seeking of the scrollable by returning false
                return false;
            } else {
                // Everything OK, hide the drawer
                drawer.slideUp();
            }
        }

        // Post data and quit on last page
        if (currentItem >= (totalItems - 1)) {
            // Load tax deduction labels to populate post_donation_instructions
            updateFormLabels();

            // Process form
            var provider = null;
            switch ($('input[name=payment_provider]:checked', '#wizard').attr('id')) {
                case 'payment-stripe':
                    provider = 'stripe';
                    handleStripeDonation();
                    break;
                case 'payment-paypal':
                    provider = 'paypal';
                    handlePayPalDonation();
                    break;
                case 'payment-gocardless':
                    provider = 'gocardless';
                    handlePopupDonation('GoCardless');
                    break;
                case 'payment-bitpay':
                    provider = 'bitpay';
                    handlePopupDonation('BitPay');
                    break;
                case 'payment-coinbase':
                    provider = 'coinbase';
                    handlePopupDonation('Coinbase');
                    break;
                case 'payment-skrill':
                    provider = 'skrill';
                    handleIFrameDonation('Skrill');
                    break;
                case 'payment-banktransfer':
                    provider = 'banktransfer';
                    handleBankTransferDonation();
                    break;
                default:
                    // Exit
            }

            // Dispatch raise_initiated_donation event
            if (!checkoutEventDispatched) {
                var ev = new CustomEvent('raise_initiated_donation', { detail: {
                    form: jQuery('#raise-form-name').val(),
                    currency: getDonationCurrencyIsoCode(),
                    amount: getTotalAmount(),
                    payment_provider: provider,
                    purpose: jQuery('input[name=purpose]:checked', '#wizard').val()
                }});
                window.dispatchEvent(ev);
                checkoutEventDispatched = true;
            }

            // Done, wait for callback functions
            return false;
        }

        // Dispatch raise_interacted_with_donation_form event
        if (!interactionEventDispatched) {
            var ev = new CustomEvent('raise_interacted_with_donation_form', { detail: {
                form: jQuery('#raise-form-name').val(),
                currency: getDonationCurrencyIsoCode(),
                amount: getDonationAmount()
            }});
            window.dispatchEvent(ev);
            interactionEventDispatched = true;
        }

        // If we're not at the end, do the following
        if (currentItem == (totalItems - 2)) {
            // On penultimate page load tax deduction labels
            updateFormLabels();

            // ... and replace "confirm" with "donate X CHF"
            setTimeout(function() { showLastItem(currentItem) }, 200);
        } else {
            // Otherwise go to next slide
            carouselNext();
        }
    });

    // Expand Recaptcha banner
    $('div.g-recaptcha-overlay', '#wizard').click(function() {
        if($(this).hasClass('expanded')) {
            $(this)
                .removeClass('expanded')
                .css('left', '0')
                .siblings().animate({ width: 70 }, 200);
        } else { 
            $(this)
                .addClass('expanded')
                .css('left', '186px')
                .siblings().animate({ width: 256 }, 200);
        }
    });

    // Focus on other amount
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

    // Other amount formatting 1: Only 0-9 and '.' are valid symbols
    $('input#amount-other').change(function() {
        var value = $(this).val().replace(/[^\d\.]/gm,'');
        $(this).val(value);
    });

    // Other amount formatting 2: Only 0-9 and '.' are valid symbols
    $('input#amount-other').keypress(function(event) {
        var keyCode = event.which;

        // Validate input (workaround for Safari)
        if (keyCode == 13) {
            $('button.confirm:first').click();
            return false;
        }
    });

    // Click on frequency labels
    $('ul#frequency label').click(function() {
        // Make new label active
        $(this).parent().parent().find('label').removeClass('active');
        $(this).addClass('active');
        frequency = $(this).siblings('input').val();

        // Hide payment options that do not support monthly
        var paymentOptions = $('#payment-providers label');
        if (frequency == 'monthly') {
            var toHide = 'amount-once';
            var toShow = 'amount-monthly';
            paymentOptions.each(function(index) {
                if (monthlySupport.indexOf($(this).attr('for')) === -1) {
                    $(this).addClass('hidden');
                    $(this).find('input').prop('checked', false);
                }
            });
        } else {
            var toHide = 'amount-monthly';
            var toShow = 'amount-once';

            // Make all options visible again
            paymentOptions.each(function(index) {
                $(this).removeClass('hidden');
            });
        }

        // Reload payment providers
        reloadPaymentProviders();

        // Switch buttons if necessary
        var buttonsToShow = $('ul#amounts li.' + toShow);
        if (buttonsToShow.length > 0) {
            // Hide buttons
            $('ul#amounts li.' + toHide)
                .addClass('hidden')
                .find('input')
                .prop('checked', false)
                .prop('disabled', true);

            // Remove active labels
            $('ul#amounts label').removeClass('active');
            
            // Show buttons
            buttonsToShow
                .removeClass('hidden')
                .find('input')
                .prop('checked', false)
                .prop('disabled', false);

            // Diable next button unless custom field is selected
            if (!$('input#amount-other').hasClass('active')) {
                disableConfirmButton(0);
            }
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

        // Remove active css class from all items
        $('ul#amounts label').removeClass("active");

        // Handle other input
        var otherInput = $('input#amount-other');
        otherInput.siblings('span.raise-error').remove();
        otherInput
            .val('')
            .removeClass('active')
            .siblings('span.input-group-addon').removeClass('active')
            .parent().removeClass('required')
            .parent().removeClass('has-error')

        // Mark this as active
        $(this).addClass("active");

        // Automatically go to next slide
        enableConfirmButton(0);
        setTimeout(function() { $('button.confirm:first').click() }, 10);
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
        var currencyString = currencies[selectedCurrency];
        $('ul#amounts>li>label').text(
            function(i, val) {
                return currencyString.replace('%amount%', $(this).prev('input').attr('value')); 
            }
        );
        $('ul#amounts span.input-group-addon').text($.trim(currencyString.replace('%amount%', '')));

        // Set new lower bound to other amount field
        var minAmount = currencyMinimums[selectedCurrency][frequency];
        jQuery('input#amount-other', '#wizard').prop('min', minAmount);

        // Reload payment providers
        reloadPaymentProviders();
    });

    $('div#payment-providers input[type=radio][name=payment_provider]').change(function() {
        // Update tax deduction labels
        updateFormLabels();
    });

    // Purpose dropdown stuff
    $('#donation-purpose input[type=radio]').change(function() {
        $('#selected-purpose').text($(this).siblings('span').text());

        // Update tax deduction text
        updateFormLabels('purpose');
    });    

    // Country dropdown stuff
    $('select#donor-country').change(function() {
        // Reload Stripe handler
        var option      = $(this).find('option:selected');
        var countryCode = option.val();

        if (!!countryCode) {
            userCountry = countryCode;

            // Make sure it's displyed correctly (autocomplete may mess with it)
            $('input#donor-country-auto').val(option.text());
            $('input[name=country_code]', '#wizard').val(countryCode);

            // Update tax deduction labels
            updateFormLabels();
        }
    });

    // Tax receipt toggle
    $('input#tax-receipt').change(function() {
        taxReceiptNeeded = $(this).is(':checked');

        // Toggle donor form display and required class
        if (taxReceiptNeeded) {
            var list = carousel.find('.slick-list');
            var newHeight = list.height() + 147;
            carousel.height(newHeight);
            list.height(newHeight);

            $('div#donor-extra-info')
                .slideDown(400, resizeCarousel)
                .find('div.optionally-required').addClass('required');
        } else {
            $('div#donor-extra-info')
                .slideUp(400, resizeCarousel)
                .find('div.optionally-required').removeClass('required');
        }

        // Update form labels
        updateFormLabels();
    });

    // Disable precheck state defined in settings on first click
    $('input.precheckable').click(function() {
        checkboxPreCheck[$(this).attr('id')] = true;
    });

    // Tipping toggle
    $('#tip').change(updateTip);
}); // End jQuery(function($) {})

/**
 * Resize carousel
 */
function resizeCarousel() {
    // Workaround to resize carousel
    carousel.slick('slickSetOption', 'draggable', false, true);
    carousel.removeAttr('style');
}

/**
 * PayPal checkout.js
 */
if (typeof paypal !== 'undefined') {
    paypal.Button.render({
        env: raiseMode == 'sandbox' ? 'sandbox' : 'production',
        commit: true,
        style: {
            label: 'checkout',  // checkout | credit | pay
            size:  'small',     // small | medium | responsive
            shape: 'pill',      // pill | rect
            color: 'blue'       // gold | blue | silver
        },
        // payment() is called when the button is clicked
        payment: function() {
            // Close modal
            jQuery('#PayPalModal').modal('hide');

            // Send form
            return new paypal.Promise(function(resolve, reject) {
                jQuery('form#donationForm').ajaxSubmit({
                    success: function(response) {
                        try {
                            if (!response.success) {
                                throw response.error || response;
                            }
    
                            // Resolve payment / billing agreement
                            var token = response.paymentID || response.token;
                            resolve(token);
                        } catch(err) {
                            // Something went wrong
                            reject(new Error(err));
                            alertError({error: err});
                        }
                    },
                    error: function(err) {
                        // Something went wrong
                        reject(new Error(err));
                        alertError({error: err});
                    }
                });
            });
        },
        // onAuthorize() is called when the buyer approves the payment
        onAuthorize: function(data) {
            // Show spinner on form
            showSpinnerOnLastButton();

            // Lock last step
            lockLastStep(true);

            // Prepare parameters
            var params = { action: "paypal_execute" };
            if (data.hasOwnProperty('paymentID') && data.hasOwnProperty('payerID')) {
                params.paymentID = data.paymentID;
                params.payerID   = data.payerID;
            } else if (data.hasOwnProperty('paymentToken')) {
                params.token = data.paymentToken;
            } else {
                alert('An error occured. Donation aborted.');
                lockLastStep(false);
                return;
            }

            // Execute payment / billing agreement
            jQuery.post(wordpress_vars.ajax_endpoint, params)
                .done(function(response) {
                    try {
                        if (!response.success) {
                            throw response.error || response;
                        }

                        // Everything worked. Show confirmation.
                        showConfirmation('paypal');
                    } catch(err) {
                        // Something went wrong
                        alertError({error: err});
                    }
                })
                .fail(function(err)  {
                    // Something went wrong
                    alertError({error: err});
                });
        },
        onCancel: function(data) {
            lockLastStep(false);
        }

    }, '#PayPalPopupButton');
}

/**
 * Auxiliary functions
 */

function updateTip() {
    var addTip = jQuery('#tip').is(':checked');

    // Change tip amount
    if (addTip) {
        // Get amount
        var amount        = getDonationAmount();
        var tipPercentage = jQuery('#tip-percentage').val();
        jQuery('#tip-amount').val(Math.floor(amount * tipPercentage) / 100);
    } else {
        jQuery('#tip-amount').val(0);
    }

    // Update button
    jQuery('button.confirm:last', '#wizard').html(getLastButtonText());
}

function isValidEmail(email) {
    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,10})+$/;
    return regex.test(email);
}

function enableConfirmButton(n) {
    jQuery('button.confirm:eq(' + n + ')').prop('disabled', false);
}

function disableConfirmButton(n) {
    jQuery('button.confirm:eq(' + n + ')').prop('disabled', true);
}

function getLastButtonText() {
    var total            = getTotalAmount();
    var currencyCode     = getDonationCurrencyIsoCode();
    var currencyAmount   = currencies[currencyCode].replace('%amount%', total);
    var buttonFinalText  = frequency == 'monthly' ? wordpress_vars.labels.donate_button_monthly : wordpress_vars.labels.donate_button_once;
    return buttonFinalText.replace('%currency-amount%', currencyAmount);
}

function showLastItem(currentItem) {
    // Change text of last confirm button
    jQuery('button.confirm:last', '#wizard').text(getLastButtonText());

    // Go to next slide
    carouselNext();
}

function getTotalAmount()
{
    var donation = getDonationAmount();
    var tip      = getTippingAmount();
    var total    = +donation + +tip;

    return (total % 1 == 0) ? total.toFixed(0) : total.toFixed(2);
}

function getDonationAmount() {
    var amount = jQuery('input[name=amount]:radio:checked', '#wizard').val();
    if (amount) {
        return amount.replace('.00', '');
    } else {
        amount = parseInt(jQuery('input#amount-other', '#wizard').val() * 100) / 100;
        return (amount % 1 == 0) ? amount.toFixed(0) : amount.toFixed(2);
    }
}

function getTippingAmount() {
    var amount = parseInt(jQuery('#tip-amount').val() * 100) / 100;
    return (amount % 1 == 0) ? amount.toFixed(0) : amount.toFixed(2);
}

function getDonationCurrencyIsoCode() {
    return selectedCurrency;
}

/**
 * Handle Stripe donation
 */
function handleStripeDonation() {
    // Change action input
    jQuery('form#donationForm input[name=action]').val('raise_redirect_html');

    raisePopup = window.open('', 'stripe-window');

    // Add form target and submit
    var form = document.querySelector('#donationForm');
    form.setAttribute('target', 'stripe-window');
    form.submit();
}

function handlePopupDonation(provider) {
    // Show spinner right away
    showSpinnerOnLastButton();

    // Change action input
    jQuery('form#donationForm input[name=action]').val('raise_redirect');

    // Get sign up URL
    jQuery('form#donationForm').ajaxSubmit({
        success: function(response, statusText, xhr, form) {
            try {
                if (!response.success) {
                    throw response.error || response;
                }

                // Show or hide note
                jQuery('#' + provider + 'Note').toggleClass("hidden", frequency === 'monthly');

                // Open URL in modal
                jQuery('#' + provider + 'PopupButton')
                    .unbind()
                    .click(function() {
                        // Open popup
                        console.log(response);
                        openRaisePopup(response.url, provider);

                        // Show "continue donation in secure" message on modal
                        jQuery('#' + provider + 'Modal .modal-body .raise_popup_closed').addClass('hidden');
                        jQuery('#' + provider + 'Modal .modal-body .raise_popup_open').removeClass('hidden');

                        // Start poll timer
                        gcPollTimer = window.setInterval(function() {
                            if (raisePopup.closed) {
                                window.clearInterval(gcPollTimer);
                                jQuery('#' + provider + 'Modal').modal('hide');
                            }
                        }, 200);
                    });

                // Show modal
                jQuery('#' + provider + 'Modal').modal('show');
            } catch (err) {
                alertError({error: err});
            }
        },
        error: alertError
    });

    lockLastStep(true);
}

function handleIFrameDonation(provider) {
    // Show spinner right away
    showSpinnerOnLastButton();

    // Change action input
    jQuery('form#donationForm input[name=action]').val('raise_redirect');

    // Get sign up URL
    jQuery('form#donationForm').ajaxSubmit({
        success: function(response, statusText, xhr, form) {
            try {
                if (!response.success) {
                    throw response.error || response;
                }

                // Open URL in modal
                jQuery('#' + provider + 'Modal .modal-body').html('<iframe src="' + response.url + '"></iframe>');

                // Show modal
                jQuery('#' + provider + 'Modal').modal('show');
            } catch (err) {
                // Something went wrong
                alertError({error: err});
            }
        },
        error: alertError
    });

    lockLastStep(true);
}

function hideModal() {
    jQuery('.raise-modal').modal('hide');
}

function openRaisePopup(url, title) {
    raisePopup = popupCenter(url, title, 420, 560);
    return false;
}

function popupCenter(url, title, w, h) {
    // Fixes dual-screen position                         Most browsers      Firefox
    var dualScreenLeft = window.screenLeft != undefined ? window.screenLeft : screen.left;
    var dualScreenTop  = window.screenTop  != undefined ? window.screenTop  : screen.top;

    var width  = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width;
    var height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height;

    var left      = ((width / 2) - (w / 2)) + dualScreenLeft;
    var top       = ((height / 2) - (h / 2)) + dualScreenTop;
    var newWindow = window.open(url, title, 'scrollbars=yes, width=' + w + ', height=' + h + ', top=' + top + ', left=' + left);

    // Puts focus on the newWindow
    if (window.focus) {
        newWindow.focus();
    }

    return newWindow;
}

function handleBankTransferDonation() {
    // Show spinner
    showSpinnerOnLastButton();

    // Change action input
    jQuery('form#donationForm input[name=action]').val('process_banktransfer');

    // Clear confirmation email (honey pot)
    jQuery('#donor-email-confirm').val('');

    if (jQuery('div.g-recaptcha', '#wizard').length) {
        // Get captcha, then send form
        grecaptcha.execute();
    } else {
        // Send form
        sendBanktransferDonation();
    }
}

function sendBanktransferDonation() {
    // Send form
    jQuery('form#donationForm').ajaxSubmit({
        success: function(response, statusText, xhr, form) {
            try {
                if (!response.success) {
                    throw response.error || response;
                }

                // Inject reference number in the success text
                jQuery('div#shortcode-content').html(
                    jQuery('div#shortcode-content').html().replace(/%reference_number%/g, response.reference)
                );

                // Everything worked! Display short code content on confirmation page
                // Change glyphicon from "spinner" to "OK" and go to confirmation page
                showConfirmation('banktransfer');
            } catch(err) {
                // Something went wrong
                alertError({error: err});
            }
        },
        error: alertError
    });

    // Disable submit button, back button, and payment options
    lockLastStep(true);
}

function handlePayPalDonation() {
    // Change action input
    jQuery('form#donationForm input[name=action]').val('raise_redirect');

    // Open modal
    jQuery('#PayPalModal').modal('show');
}

function showSpinnerOnLastButton() {
    jQuery('button.confirm:last', '#wizard')
        .html('<span class="glyphicon glyphicon-refresh glyphicon-refresh-animate" aria-hidden="true"></span>')
        .removeClass('donation-continue');
}

function lockLastStep(locked) {
    jQuery('#donation-submit').prop('disabled', locked);
    jQuery('#donation-go-back').prop('disabled', locked);
    jQuery('div.donor-info input', '#payment-method-item').prop('disabled', locked);
    jQuery('div.donor-info textarea', '#payment-method-item').prop('disabled', locked);
    jQuery('div.donor-info button', '#payment-method-item').prop('disabled', locked);
    jQuery('input', '#payment-providers').prop('disabled', locked);
    jQuery('div.checkbox input', '#payment-method-item').prop('disabled', locked);

    if (!locked) {
        // Make sure tax deduction stays disabled when not possible
        jQuery('#tax-receipt').prop('disabled', taxReceiptDisabled);

        // Restore submit button
        jQuery('button.confirm:last', '#wizard')
            .html(getLastButtonText())
            .addClass('donation-continue');
    }
}

function showConfirmation(paymentProvider) {
    // Hide spinner
    jQuery('button.confirm:last', '#wizard')
        .removeClass('donation-continue')
        .html('<span class="glyphicon glyphicon-ok" aria-hidden="true"></span>');

    // Move to confirmation page after 1 second
    setTimeout(carouselNext, 1000);

    // Dispatch raise_completed_donation event
    var ev = new CustomEvent('raise_completed_donation', { detail: {
        form: jQuery('#raise-form-name').val(),
        currency: getDonationCurrencyIsoCode(),
        amount: getTotalAmount(),
        payment_provider: paymentProvider,
        purpose: jQuery('input[name=purpose]:checked', '#wizard').val()
    }});
    window.dispatchEvent(ev);

    // Update fundraiser widgets if present on the same page
    if (typeof updateFundraiser === 'function') {
        updateFundraiser();
    }
}

function alertError(response) {
    if (typeof response === "object" && typeof response.error === 'string') {
        alert(response.error);
    } else {
        alert(wordpress_vars.error_messages.connection_error);
    }

    // Enable buttons
    lockLastStep(false);
}

function carouselNext() {
    var nextItem = jQuery('#wizard div.active').index() + 1;

    if (nextItem  > totalItems) {
        return false;
    }

    // Move carousel
    carousel.slick('slickNext');
    
    // Update progress bar
    updateProgressBar(nextItem);
}


function carouselPrev() {
    var prevItem = carousel.slick('slickCurrentSlide') - 1;

    if (prevItem < 0) {
        return false;
    }

    // Move carousel
    carousel.slick('slickPrev');
    
    // Update progress bar
    updateProgressBar(prevItem);
}

function updateProgressBar(currentItem) {
    var listItems = jQuery("#progress li");
    listItems.removeClass("active completed");
    listItems.filter(function(index) { return index < currentItem }).addClass("completed");
    listItems.eq(currentItem).addClass("active");

    // Make previous steps clickable, unless we're done
    listItems.unbind('click').removeClass('clickable');
    if (currentItem < totalItems - 1) {
        listItems.slice(0, currentItem).each(function(index) {
            jQuery(this)
                .addClass('clickable')
                .click(function() {
                    // Move carousel
                    carousel.slick('slickGoTo', index);

                    // Update progress bar
                    updateProgressBar(index);
                });
        });
    }
}

function getDonorInfo(name) {
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
function getCountriesByCurrency(currency) {
    var mapping = wordpress_vars.currency2country;

    if (currency in mapping) {
        return mapping[currency];
    } else {
        return [];
    }
}

/**
 * Show/hide payment providers
 */
function reloadPaymentProviders() {
    // Hide payment providers that don't support the current donation
    var formObj = getFormAsObject();
    jQuery('#payment-providers label').each(function() {
        var providerLabel = jQuery(this).find('input[name="payment_provider"]').val();
        var tempObj       = jQuery.extend({}, formObj, {"payment_provider": providerLabel});
        var display       = jsonLogic.apply(wordpress_vars.payment_provider_display_rule, tempObj);
        jQuery(this).toggle(display);
    });

    // Pre-select first provider
    jQuery('#payment-providers label:not(:hidden):first input').prop('checked', true);
}

/**
 * Apply JsonLogic and JSON.parse result. Useful for prvenenting evaluation of `if` statement values
 * 
 * @param Object rule 
 * @param Object data 
 */
function applyJsonLogicAndParse(rule, data) {
    var result = jsonLogic.apply(rule, data);

    return JSON.parse(result);
}

/**
 * Update form labels (payment provider tooltip, tax receipt checkbox, post donation instructions)
 * 
 * @param string source Name of widget that triggered this (optional)
 */
function updateFormLabels(source) {
    // Get all form contents
    var formObj = getFormAsObject();

    // Infer properties
    var postDonationInstructions = jsonLogic.apply(wordpress_vars.post_donation_instructions_rule, formObj);
    var shareDataCheckboxState   = applyJsonLogicAndParse(wordpress_vars.share_data_rule, formObj);
    var tipCheckboxState         = applyJsonLogicAndParse(wordpress_vars.tip_rule, formObj);
    var taxReceiptCheckboxState  = applyJsonLogicAndParse(wordpress_vars.tax_receipt_rule, formObj);
    var bankAccount              = applyJsonLogicAndParse(wordpress_vars.bank_account_rule, formObj) || {};
    var bankAccountProperties    = bankAccount.hasOwnProperty('details') ? bankAccount.details : {};

    // Update payment provider tooltips
    jQuery('#payment-providers label').each(function() {
        var providerLabel = jQuery(this).find('input[name="payment_provider"]').val();
        var tempObj       = jQuery.extend({}, formObj, {"payment_provider": providerLabel});
        var tooltip       = jsonLogic.apply(wordpress_vars.payment_provider_tooltip_rule, tempObj);
        jQuery(this).find('div[data-toggle="tooltip"]').attr('data-original-title', tooltip);
    });

    // Update checkbox states when purpose changes (otherwise no need)
    if (source === 'purpose' && !!jQuery('input[name=purpose]:checked', '#wizard').val()) {
        updateCheckboxState('share-data', shareDataCheckboxState, formObj);
        updateCheckboxState('tip', tipCheckboxState, formObj);
    }
    updateCheckboxState('tax-receipt', taxReceiptCheckboxState, formObj);

    // Update offered share data state
    jQuery('#share-data-offered').val(
        jQuery('#share-data-form-group').is(':not(:hidden)') ? 1 : 0
    );

    // Update offered tip state
    jQuery('#tip-offered').val(
        jQuery('#tip-form-group').is(':not(:hidden)') ? 1 : 0
    );

    // Update post donation instructions with nl2br
    if (postDonationInstructions !== null) {
        var postDonationInstructionText = nl2br(replaceDonationPlaceholders(postDonationInstructions, formObj, bankAccountProperties));
        jQuery('div#shortcode-content').html(postDonationInstructionText);
        jQuery('input[name=post_donation_instructions]').val(postDonationInstructionText);
    }

    // Update tip
    updateTip();
}

/**
 * Update checkbox state
 * 
 * @param string id      ID of form element
 * @param Object state   Object containing the properties `label` and `disabled`
 * @param Object formObj Form object
 */
function updateCheckboxState(id, state, formObj) {
    var element = jQuery('input#' + id);

    // Update tip percentage
    if (id === 'tip' && state && state.hasOwnProperty('tip_percentage')) {
        jQuery('#tip-percentage').val(state.tip_percentage);
    }

    // Update checkbox state
    if (state && state.hasOwnProperty('checked')) {
        if (!checkboxPreCheck[id] && !element.is(':checked') && state.checked) {
            element.prop('checked', true).change();
        }
    } else {
        // Uncheck
        if (id === 'tip' && element.is(':checked')) {
            element.prop('checked', false).change();
        }
    }

    // Enable/disable checkbox
    var disabled = state && state.hasOwnProperty('disabled') && !!state.disabled;
    if (id === 'tax-receipt') {
        // Update global tax deduction variable
        taxReceiptDisabled = disabled;

        // Collapse address details if open
        if (disabled && element.is(':checked')) {
            element.click();
        }
    }
    element.prop('disabled', disabled);

    // Update checkbox label
    if (state && state.label) {
        element.parent().parent().parent().parent().show();
        state.label = replaceDonationPlaceholders(state.label, formObj);
        jQuery('span#' + id + '-text').html(state.label);
    } else {
        // Hide checkbox
        element.prop('checked', false);
        element.parent().parent().parent().parent().hide();
    }
}

/**
 * Replace donation placeholders
 */
function replaceDonationPlaceholders(label, formObj, accountData) {
    // Replace %bank_account_formatted%
    if (!jQuery.isEmptyObject(accountData)) {
        // Get amount string
        var amount = currencies.hasOwnProperty(formObj.currency)
            ? currencies[formObj.currency].replace('%amount%', formObj.amount)
            : formObj.currency + ' ' + formObj.amount;
        var accountDataString = '<strong>' + wordpress_vars.labels.amount + '</strong>: ' + amount + "\n";

        // Add bank details
        accountDataString += Object.keys(accountData).map(function(key) {
            return '<strong>' + key + '</strong>: ' + accountData[key];
        }).join("\n");

        label = label.replace('%bank_account_formatted%', accountDataString);
    }

    Object.keys(formObj).forEach(function(key, index) {
       var replace = '%' + key + '%';
       var regex   = new RegExp(replace, "g");
       label       = label.replace(regex, formObj[key]);
    });

    return label;
}

function getFormAsObject() {
    var formObj = {};
    jQuery.each(jQuery('#donationForm').serializeArray(), function(_, kv) {
        if (formObj.hasOwnProperty(kv.name)) {
          formObj[kv.name] = jQuery.makeArray(formObj[kv.name]);
          formObj[kv.name].push(kv.value);
        } else {
          formObj[kv.name] = kv.value;
        }
    });

    // Delete internal values email-confirm (honey pot), action, form, mode, locale
    delete formObj['email-confirm'];
    delete formObj['action'];
    delete formObj['form'];
    delete formObj['mode'];
    delete formObj['locale'];

    // Merge amount and amount-other
    if (!formObj.hasOwnProperty('amount')) {
        formObj.amount = formObj.amount_other;
    }
    delete formObj.amount_other;

    // Save localized values for frequency, payment_provider, purpose to `*_label` (except `country` for `country_code`)
    formObj.frequency_label        = jQuery('input[name=frequency][value=' + formObj.frequency + ']').siblings('label').text();
    formObj.payment_provider_label = jQuery('input[name=payment_provider][value=' + formObj.payment_provider.replace(' ', '\\ ') + ']').siblings('span').text();
    if (formObj.hasOwnProperty('country_code')) {
        formObj.country = formObj.country_code ? jQuery('select#donor-country option[value=' + formObj.country_code.toUpperCase() + ']').text() : '';
    }
    if (formObj.hasOwnProperty('purpose')) {
        formObj.purpose_label = jQuery('input[name=purpose][value=' + formObj.purpose.replace(' ', '\\ ') + ']').siblings('span').text();
    }

    // Localize booleans
    formObj.tip_label         = formObj.hasOwnProperty('tip')         ? wordpress_vars.labels.yes : wordpress_vars.labels.no;
    formObj.share_data_label  = formObj.hasOwnProperty('share_data')  ? wordpress_vars.labels.yes : wordpress_vars.labels.no;
    formObj.mailinglist_label = formObj.hasOwnProperty('mailinglist') ? wordpress_vars.labels.yes : wordpress_vars.labels.no;
    formObj.tax_receipt_label = formObj.hasOwnProperty('tax_receipt') ? wordpress_vars.labels.yes : wordpress_vars.labels.no;

    // Add account property
    formObj.account = jsonLogic.apply(wordpress_vars.payment_provider_account_rule, formObj);

    return formObj;
}

/**
 * nl2br function from PHP
 */
function nl2br(str, isXhtml) {
    if (typeof str === 'undefined' || str === null) {
        return '';
    }
    // Adjust comment to avoid issue on locutus.io display
    var breakTag = (isXhtml || typeof isXhtml === 'undefined') ? '<br ' + '/>' : '<br>';
    return (str + '').replace(/(\r\n|\n\r|\r|\n)/g, breakTag + '$1');
}

/**
 * Trigger form loaded event
 */
function raiseTriggerFormLoadedEvent() {
    var ev = new CustomEvent('raise_loaded_donation_form', { detail: {
        form: document.getElementById("raise-form-name").value
    }});
    window.dispatchEvent(ev);
}

/**
 * Show appropriate error message
 */
function getErrorMessage(errors) {
    if (errors.hasOwnProperty('amount')) {
        var minAmount      = currencyMinimums[selectedCurrency][frequency];
        var currencyAmount = currencies[selectedCurrency].replace('%amount%', minAmount);
        return errors['amount'].replace(/%minimum_amount%/g, currencyAmount);
    }

    if (errors.hasOwnProperty('amount-other')) {
        var minAmount      = currencyMinimums[selectedCurrency][frequency];
        var currencyAmount = currencies[selectedCurrency].replace('%amount%', minAmount);
        return errors['amount-other'].replace(/%minimum_amount%/g, currencyAmount);
    }

    if (errors.hasOwnProperty('donor-email')) {
        return errors['donor-email'];
    }

    return wordpress_vars.error_messages['missing_fields'];
}
