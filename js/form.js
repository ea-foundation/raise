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
var placeholders          = {
    "DE": "Anderer Betrag"
}
var buttonFinalText       = '%curprefix%%amount% %curpostfix% spenden »';
var buttonConfirmText     = 'Bestätigen »';
var totalItems = 0;

/**
  * Stripe setup
  */ 
var stripeHandler = StripeCheckout.configure({
    key: 'pk_test_6pRNASCoBOKtIshFeQd4XMUh',
    image: wordpress_vars.plugin_path + 'images/eas-logo.png',
    locale: 'auto',
    token: function(token) {
      // Use the token to create the charge with a server-side script.
      // You can access the token ID with `token.id`
      //console.log(token);
      var tokenInput   = jQuery('<input type="hidden" name="stripeToken" />').val(token.id);
      var emailInput   = jQuery('<input type="hidden" name="stripeEmail" />').val(token.email);

      // Show spinner
      jQuery('button.confirm:last', '#wizard').html('<span class="glyphicon glyphicon-refresh glyphicon-refresh-animate" aria-hidden="true"></span>');

      // Send form
      jQuery('form#donationForm').append(tokenInput).append(emailInput).ajaxSubmit({
            success: function(responseText, statusText, xhr, form) {
                if (responseText == 'success') {
                    // Everything worked! Change glyphicon from "spinner" to "OK" and go to confirmation page
                    jQuery('button.confirm:last', '#wizard').html('<span class="glyphicon glyphicon-ok" aria-hidden="true"></span>');
                    setTimeout(function() { carouselNext(); }, 1000);
                } else {
                    // Something went wrong, show on confirmation page
                    alert(responseText);

                    // Enable buttons
                    jQuery('#donationSubmit').prop('disabled', false);
                    jQuery('#donationGoBack').prop('disabled', false);
                    jQuery('#payment-method div.radio input').prop('disabled', false);

                    //TODO Reset text of submit button
                }
            }
      });

      // Disable submit button, back button, and payment options
      jQuery('#donationSubmit').prop('disabled', true);
      jQuery('#donationGoBack').prop('disabled', true);
      jQuery('#payment-method div.radio input').prop('disabled', true);

      return false;
    }
});
// preload stripe image
var stripeImage = new Image();
stripeImage.src = wordpress_vars.plugin_path + 'images/eas-logo.png';





/**
  * Form setup
  */
jQuery(document).ready(function() {

    totalItems = jQuery('#wizard .item').length;
    //var root = jQuery("#wizard").scrollable().navigator("#status");
   
    // some variables that we need
    //var api = root.scrollable(); 
    var drawer = jQuery("#drawer");

    // page count
    //var pageCount = jQuery('div#wizard div.page').length;
    jQuery('button.unconfirm').click(function(event) {
        var currentItem = jQuery('#wizard div.active').index();

        if (currentItem  < 1) {
            return false;
        }

        // go back
        jQuery('#donation-carousel').carousel('prev');

        // update status bar
        jQuery("#status li").removeClass("active").eq(currentItem - 1).addClass("active");
    });

    // validation logic is done inside the onBeforeSeek callback
    jQuery('button.confirm').click(function(event) {
        var currentItem = jQuery('div.active', '#wizard').index() + 1;

        // check contents
        if (currentItem <= totalItems) {
            // Get all fields inside the page
            var inputs = jQuery('div.item.active :input', '#wizard');

            // Remove errors
            inputs.siblings('span.eas-error').remove();
            inputs.parent().parent().removeClass('has-error');

            //alert('There are ' + inputs.length + ' inputs');

            // Get all required fields inside the page
            var reqInputs = jQuery('div.item.active .required :input', '#wizard');
            // ... which are empty
            var empty = reqInputs.filter(function() {
                return jQuery(this).val().replace(/\s*/g, '') == '';
            });
            // unchecked radio groups
            var emptyRadios = jQuery('div.item.active .required:has(:radio):not(:has(:radio:checked))', '#wizard');

            //console.log(emptyRadios);

            // if there are empty fields, then
            if (empty.length + emptyRadios.length) {
                // slide down the drawer
                drawer.slideDown(function()  {     
                    // colored flash effect
                    drawer.css("backgroundColor", "#0078C1");
                    setTimeout(function() { drawer.css("backgroundColor", "#fff"); }, 1000);
                });

                // add a error CSS for empty & required fields
                empty.each(function(index) {
                    jQuery(this).attr('aria-describedby', 'inputError2Status' + index)
                    jQuery(this).parent().append('<span class="eas-error glyphicon glyphicon-remove form-control-feedback" aria-hidden="true"></span><span id="inputError2Status' + index + '" class="eas-error sr-only">(error)</span>');
                    jQuery(this).parent().parent().addClass('has-error');
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
            if (jQuery('input[name=payment]:checked', '#wizard').attr('id') == 'payment-creditcard') {
                handleCreditCardDonation();
            } else {
                //FIXME Should be AJAX
                jQuery('#donationForm').submit();
            }
            return;
            //TODO if third party payment redirect 
        }

        if (currentItem == (totalItems - 2)) {
            // on penultimate page replace "confirm" with "donate X CHF"
            var foo = setTimeout(function() { showLastItem(currentItem) }, 200);
        } else {
            // update status bar
            jQuery("#status li").removeClass("active").eq(currentItem).addClass("active");
            // show next slide
            jQuery('#donation-carousel').carousel('next');
        }
    });

    // check radio button and show confirm button
    jQuery('input#amount-other').focus(function() {
        jQuery('ul#amounts label').removeClass("active");
        jQuery('ul#amounts input:radio').prop('checked', false);
        jQuery(this).addClass("active").parent().addClass('required');
        jQuery(this).siblings('span.input-group-addon').addClass('active');
        enableConfirmButton(0);
    });
    jQuery('ul#amounts label').click(function() {
        jQuery('ul#amounts label').removeClass("active");
        jQuery('input#amount-other')
            .val('')
            .removeClass("active")
            .siblings('span.input-group-addon').removeClass('active')
            .parent().removeClass("required");
        jQuery(this).addClass("active");
        enableConfirmButton(0);
        jQuery('button.confirm:first').click();
    });

    // currency stuff
    jQuery('#donation-currency ul li a').click(function() {
        // emove old currency
        jQuery('.cur', '#wizard').text('');
        
        // Update and close dropdown
        jQuery('#selected-currency').html(jQuery(this).html());
        jQuery(this).parent().parent().parent().removeClass('open');

        // Set new currency on buttons and on custom input field
        var currencyCode   = jQuery(this).find('img').attr('alt');
        var currencyString = currencies[currencyCode];
        jQuery('ul#amounts>li>label').text(
            function(i, val) {
                return currencyString.replace('%amount%', jQuery(this).prev('input').attr('value')); 
            }
        );
        jQuery('span.input-group-addon').text(jQuery.trim(currencyString.replace('%amount%', '')));

        // Set curreny code to hidden field
        jQuery('input[name=currency]').attr('value', jQuery.trim(jQuery(this).text()));
        return false;
    });

    // Other amount placeholder
    jQuery('input#amount-other').focus(function() {
        jQuery(this).attr('placeholder', '');
    }).blur(function() {
        var placeholder = placeholders['DE'];
        jQuery(this).attr('placeholder', placeholder);
    }).siblings('span.input-group-addon').click(function() {
        jQuery(this).siblings('input').focus();
    });

    // show div with payment details
    var paymentPanels = jQuery('div#payment-method div.radio > div');
    jQuery('div#payment-method input[name=payment]').change(function() {
        // slide all panels up
        paymentPanels.slideUp();
        // ... except the one that was clicked
        jQuery(this).parent().next().slideDown();

        // remove required class from all panels
        paymentPanels.children('div').removeClass('required');
        // ... except the one taht was clicked
        jQuery(this).parent().next().children('div').addClass('required');
    });
});




/**
 * Auxiliary functions
 */

function enableConfirmButton(n)
{
    jQuery('button.confirm:eq(' + n + ')').removeAttr('disabled');
}

function showLastItem(currentItem)
{
    // Change text of last confirm button
    var amount          = getDonationAmount();
    var currency        = getDonationCurrencySymbol();
    var currencyPrefix  = jQuery.inArray(currency, prefixCurrencySymbols) >= 0 ? currency : '';
    var currencyPostfix = currencyPrefix == '' ? currency : '';
    var buttonText = buttonFinalText.replace('%amount%', amount)
        .replace('%curprefix%', currencyPrefix)
        .replace('%curpostfix%', currencyPostfix);
    jQuery('button.confirm:last', '#wizard').text(buttonText);
    
    // update status bar
    jQuery("#status li").removeClass("active").eq(currentItem).addClass("active");

    // show next slide
    jQuery('#donation-carousel').carousel('next');
}

function getDonationAmount()
{
    var amount = jQuery('input[name=amount]:radio:checked', '#wizard').val();
    if (amount) {
        return amount.replace('.00', '');
    } else {
        amount = parseInt(jQuery('input#amount-other', '#wizard').val() * 100);
        if (jQuery.isNumeric(amount) && amount >= 100) {
            return amount / 100;
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

/*function getDonorEmail()
{
    return jQuery('input#email', '#wizard').val();
}*/

function handleCreditCardDonation()
{
    stripeHandler.open({
        name: 'Stiftung für Effektiven Altruismus',
        description: 'Spende',
        amount: getDonationAmount() * 100,
        currency: getDonationCurrencyIsoCode()
        //email: getDonorEmail()
    });
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













