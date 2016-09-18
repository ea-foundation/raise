/**
  * Settings
  */
var easFormName     = 'default'; // This gets overwritten by a script embedded in the form
var easMode         = 'live';    // This gets overwritten by a script embedded in the form
var currencies      = wordpress_vars.amount_patterns;
var stripeHandlers  = null;
var buttonFinalText = wordpress_vars.donate_button_text + ' »';
var totalItems      = 0;
var slideTransitionInAction = false;
var otherAmountPlaceholder  = null;




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
    stripeHandler = StripeCheckout.configure({
        key: wordpress_vars.stripe_public_keys[easFormName][easMode],
        image: wordpress_vars.plugin_path + 'images/logo.png',
        color: '#255A8E',
        locale: 'auto',
        token: function(token) {
            //console.log("my object: %o", token);
            // Use the token to create the charge with a server-side script.
            // You can access the token ID with `token.id`
            var tokenInput = jQuery('<input type="hidden" name="stripeToken" />').val(token.id);
            //var emailInput = jQuery('<input type="hidden" name="stripeEmail" />').val(token.email);

            // Show spinner
            showSpinnerOnLastButton();

            // Send form
            jQuery('form#donationForm').append(tokenInput).ajaxSubmit({
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

        //$('#donation-carousel').carousel('prev');

        // update status bar
        //$("#status li").removeClass("active").eq(currentItem - 1).addClass("active");
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

        // check contents
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
                    $(this).attr('aria-describedby', 'inputError2Status' + index)
                    $(this).parent().append('<span class="eas-error glyphicon glyphicon-remove form-control-feedback" aria-hidden="true"></span><span id="inputError2Status' + index + '" class="eas-error sr-only">(error)</span>');
                    $(this).parent().parent().addClass('has-error');
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

    // currency stuff
    $('#donation-currency ul label').click(function() {
        var currencyCode = $(this).find('input').val();

        // Remove old currency
        $('.cur', '#wizard').text('');
        
        // Update and close dropdown
        $('#selected-currency-flag')
            .removeClass()
            .addClass($(this).find('img').prop('class'))
            .prop('alt', $(this).find('img').prop('alt'));
        $('#selected-currency').text(currencyCode);
        $(this).parent().parent().parent().removeClass('open');

        // Set new currency on buttons and on custom input field
        var currencyString = currencies[easFormName][currencyCode];
        $('ul#amounts>li>label').text(
            function(i, val) {
                return currencyString.replace('%amount%', $(this).prev('input').attr('value')); 
            }
        );
        $('span.input-group-addon').text($.trim(currencyString.replace('%amount%', '')));
    });

    // Donation purpose stuff
    $('#donation-purpose input[type=radio]').change(function() {
        $('#selected-purpose').text($(this).parent().contents().filter(function() {
            return this.nodeType == 3;
        }).text());
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
        // Toggle donor form display and required class
        if ($('div#donor-extra-info').css('display') == 'none') {
            $('div#donor-extra-info').slideDown();
            $('div#donor-extra-info div.optionally-required').addClass('required');
        } else {
            $('div#donor-extra-info').slideUp();
            $('div#donor-extra-info div.optionally-required').removeClass('required');
        }
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
    return jQuery('input[name=currency]:checked').val();
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

    // Disable confirm button, email and checkboxes
    lockLastStep(true);

    try {
        // Get payKey
        jQuery.post(wordpress_vars.ajax_endpoint, {
            action: 'paypal_paykey',
            form: getFormName(),
            mode: getFormMode(),
            language: getFormLanguage(),
            email: getDonorInfo('email'),
            amount: getDonationAmount(),
            currency: getDonationCurrencyIsoCode(),
            purpose: getDonorSelection('purpose'),
            name: getDonorInfo('name'),
            address: getDonorInfo('address'),
            zip: getDonorInfo('zip'),
            city: getDonorInfo('city'),
            country: getDonorInfo('country')
        }).done(function(responseText) {
            // On success
            var response = JSON.parse(responseText);
            if (!('success' in response) || !response['success']) {
                var message = 'error' in response ? response['error'] : responseText;
                throw new Error(message);
            }

            // Insert pay key in PayPal form
            jQuery('input[id=paykey]').val(response['paykey']);
            // Open lightbox
            jQuery('input[id=submitBtn]').click();
        }).fail(function(responseText) {
            // Should only happen on internal server error
            throw new Error(responseText);
        });
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

function getDonorSelection(name)
{
    return jQuery('input[name=' + name + ']:checked').val();
}

function getFormName()
{
    return jQuery('input#eas-form-name').val();
}

function getFormMode()
{
    return jQuery('input#eas-form-mode').val();
}

function getFormLanguage()
{
    return jQuery('input#eas-form-language').val();
}













