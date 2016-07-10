/**
  * Settings
  */
var prefixCurrencySymbols = ['$', '£', 'CHF'];
var currencies = {
    "EUR": "%amount% €",
    "CHF": "CHF %amount%",
    "GBP": "£%amount%",
    "USD": "$%amount%"
};
var placeholders = {
    "DE": "Anderer Betrag"
}
var buttonFinalText   = '%currency-amount% spenden »';
var buttonConfirmText = 'Bestätigen »';
var totalItems  = 0;
var slideTransitionInAction = false;

/**
  * Stripe setup
  */ 
var stripeHandler = StripeCheckout.configure({
    key: wordpress_vars.stripe_public_key,
    image: wordpress_vars.plugin_path + 'images/eas-logo.png',
    color: 'white',
    locale: 'auto',
    token: function(token) {
        console.log("my object: %o", token);
        // Use the token to create the charge with a server-side script.
        // You can access the token ID with `token.id`
        var tokenInput = jQuery('<input type="hidden" name="stripeToken" />').val(token.id);
        //var emailInput = jQuery('<input type="hidden" name="stripeEmail" />').val(token.email);

        // Show spinner
        jQuery('button.confirm:last', '#wizard').html('<span class="glyphicon glyphicon-refresh glyphicon-refresh-animate" aria-hidden="true"></span>');

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
                    showConfirmation();
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
// preload stripe image
var stripeImage = new Image();
stripeImage.src = wordpress_vars.plugin_path + 'images/eas-logo.png';





/**
  * Form setup
  */
jQuery(document).ready(function($) {

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

        // go back
        $('#donation-carousel').carousel('prev');

        // update status bar
        $("#status li").removeClass("active").eq(currentItem - 1).addClass("active");
    });

    // Prevent interaction durign carousel slide
    $('#donation-carousel').on('slide.bs.carousel', function () {
        slideTransitionInAction = true;
    });
    $('#donation-carousel').on('slid.bs.carousel', function () {
        slideTransitionInAction = false;
    });

    // Validation logic is done inside the onBeforeSeek callback
    $('button.confirm').click(function(event) {
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
                return $(this).val().replace(/\s*/g, '') == '' || ($(this).attr('type') == 'email' && !isValidEmail($(this).val().trim()));
            });

            // Unchecked radio groups
            var emptyRadios = $('div.item.active .required:has(:radio):not(:has(:radio:checked))', '#wizard');

            // If there are empty fields, then
            if (empty.length + emptyRadios.length) {
                // slide down the drawer
                drawer.slideDown(function()  {     
                    // Colored flash effect
                    drawer.css("backgroundColor", "#0078C1");
                    setTimeout(function() { drawer.css("backgroundColor", "#fff"); }, 1000);
                });

                // Add a error CSS for empty & required fields
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
                default:
                    $('#donationForm').ajaxSubmit();
            }

            // Done, wait for callback functions
            return false;
        }

        if (currentItem == (totalItems - 2)) {
            // on penultimate page replace "confirm" with "donate X CHF"
            var foo = setTimeout(function() { showLastItem(currentItem) }, 200);
        } else {
            // update status bar
            $("#status li").removeClass("active").eq(currentItem).addClass("active");
            // show next slide
            $('#donation-carousel').carousel('next');
        }
    });

    // Click on other amount
    $('input#amount-other').focus(function() {
        $('ul#amounts label').removeClass("active");
        $('ul#amounts input:radio').prop('checked', false);
        $(this).addClass("active").parent().addClass('required');
        $(this).siblings('span.input-group-addon').addClass('active');
        enableConfirmButton(0);
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

        $('ul#amounts label').removeClass("active");
        $('input#amount-other')
            .val('')
            .removeClass("active")
            .siblings('span.input-group-addon').removeClass('active')
            .parent().removeClass("required");
        $(this).addClass("active");
        enableConfirmButton(0);
        $('button.confirm:first').click();
    });

    // currency stuff
    $('#donation-currency ul li a').click(function() {
        // Remove old currency
        $('.cur', '#wizard').text('');
        
        // Update and close dropdown
        $('#selected-currency').html($(this).html());
        $(this).parent().parent().parent().removeClass('open');

        // Set new currency on buttons and on custom input field
        var currencyCode   = $(this).find('img').attr('alt');
        var currencyString = currencies[currencyCode];
        $('ul#amounts>li>label').text(
            function(i, val) {
                return currencyString.replace('%amount%', $(this).prev('input').attr('value')); 
            }
        );
        $('span.input-group-addon').text($.trim(currencyString.replace('%amount%', '')));

        // Set curreny code to hidden form field
        $('input#donationCurrency').attr('value', $.trim($(this).text()));
        return false;
    });

    // Other amount placeholder
    $('input#amount-other').focus(function() {
        $(this).attr('placeholder', '');
    }).blur(function() {
        var placeholder = placeholders['DE'];
        $(this).attr('placeholder', placeholder);
    }).siblings('span.input-group-addon').click(function() {
        $(this).siblings('input').focus();
    });

    // show div with payment details
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

function getLastButtonText()
{
    var amount         = getDonationAmount();
    var currencyCode   = getDonationCurrencyIsoCode();
    var currencyAmount = currencies[currencyCode].replace('%amount%', amount);
    return buttonFinalText.replace('%currency-amount%', currencyAmount);
}

function showLastItem(currentItem)
{
    // Change text of last confirm button
    jQuery('button.confirm:last', '#wizard').text(getLastButtonText());
    
    // Update status bar
    jQuery("#status li").removeClass("active").eq(currentItem).addClass("active");

    // Show next slide
    jQuery('#donation-carousel').carousel('next');
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
            return 15; // default donation
        }
    }
}

function getDonationCurrencySymbol()
{
    return jQuery('#selected-currency > img').attr('alt');
    //return jQuery('select#currency option:selected', '#wizard').text();
}

function getDonationCurrencyIsoCode()
{
    return jQuery('input[name=currency]').attr('value');
    //return jQuery('select#currency option:selected', '#wizard').val();
}

function getDonorEmail()
{
    return jQuery('input#donation-email').val();
}

/**
 * Handle Stripe donation
 */
function handleStripeDonation()
{
    stripeHandler.open({
        name: wordpress_vars.contact_name,
        description: 'Spende',
        amount: getDonationAmount() * 100,
        currency: getDonationCurrencyIsoCode(),
        email: getDonorEmail()
    });
}

/**
 * Handle Paypal donation
 */
function handlePaypalDonation()
{
    // Show spinner right away
    jQuery('button.confirm:last', '#wizard').html('<span class="glyphicon glyphicon-refresh glyphicon-refresh-animate" aria-hidden="true"></span>');

    // Disable confirm button, email and checkboxes
    jQuery('#donation-submit').prop('disabled', true);
    jQuery('input#donation-email').prop('disabled', true);
    jQuery('#donation-go-back').prop('disabled', true);
    jQuery('#payment-method-item div.radio input').prop('disabled', true);

    try {
        // Get payKey
        jQuery.post(wordpress_vars.ajax_endpoint, {
            action: 'paypal_paykey',
            email: getDonorEmail(),
            amount: getDonationAmount(),
            currency: getDonationCurrencyIsoCode()
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
        // Make paypal form and submit (redirect)
        /*var qsConnector = location.href.indexOf('?') > -1 ? '&' : '?';
        var returnUrl   = location.href + qsConnector + 'success=1';
        var form        = jQuery("<form/>", { action: wordpress_vars.paypal_url, method: 'post', style: 'display: none' });
        form.append([
            jQuery("<input>",  { type: 'hidden',  name: 'cmd', value: '_donations' }),
            jQuery("<input>",  { type: 'hidden',  name: 'business', value: wordpress_vars.paypal_id }),
            jQuery("<input>",  { type: 'hidden',  name: 'lc', value: 'CH' }),
            jQuery("<input>",  { type: 'hidden',  name: 'item_name', value: wordpress_vars.contact_name }),
            jQuery("<input>",  { type: 'hidden',  name: 'currency_code', value: getDonationCurrencyIsoCode() }),
            jQuery("<input>",  { type: 'hidden',  name: 'amount', value: getDonationAmount() }),
            jQuery("<input>",  { type: 'hidden',  name: 'email', value: getDonorEmail() }),
            jQuery("<input>",  { type: 'hidden',  name: 'no_note', value: 1 }),
            jQuery("<input>",  { type: 'hidden',  name: 'no_shipping', value: 2 }),
            jQuery("<input>",  { type: 'hidden',  name: 'rm', value: 1 }),
            jQuery("<input>",  { type: 'hidden',  name: 'return', value: returnUrl })
        ]).appendTo('body').submit();*/
    } catch (ex) {
        alert(ex.message);
    }
}

function lockLastStep(locked)
{
    jQuery('#donation-submit').prop('disabled', locked);
    jQuery('#donation-go-back').prop('disabled', locked);
    jQuery('input#donation-email').prop('disabled', locked);
    jQuery('#payment-method-item div.radio input').prop('disabled', locked);

    if (!locked) {
        // Restore submit button
        jQuery('button.confirm:last', '#wizard').html(getLastButtonText());
    }
}

function showConfirmation()
{
    jQuery('button.confirm:last', '#wizard').html('<span class="glyphicon glyphicon-ok" aria-hidden="true"></span>');
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
    jQuery("#status li").removeClass("active").eq(currentItem).addClass("active");
}


/*function carouselPrev()
{
    var currentItem = jQuery('#wizard div.active').index() - 1;

    if (currentItem  < 0) {
        return false;
    }

    // Move carousel
    jQuery('#donation-carousel').carousel('prev');
    // Update status bar
    jQuery("#status li").removeClass("active").eq(currentItem).addClass("active");
}
*/













