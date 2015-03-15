

var buttonFinalText = '%currency% %amount% spenden »';
var buttonConfirmText = 'Bestätigen »';

$(document).ready(function() {
    var root = $("#wizard").scrollable().navigator("#status");
   
    // some variables that we need
    var api = root.scrollable(), drawer = $("#drawer");

    // page count
    var pageCount = $('div#wizard div.page').length;

    // validation logic is done inside the onBeforeSeek callback
    api.onBeforeSeek(function(event, i) {
        // no skipping
        var currentI = api.getIndex();
        /*if (i - currentI > 1) {
            return false;
        }*/

        // if we are going 1 step backwards there is no need for validation, otherwise...
        if (currentI < i) {
             // 1. get current page
             var page = root.find(".page").eq(api.getIndex()),

             // 2. .. and all required fields inside the page
             inputs = page.find(".required :input").removeClass("error"),

             // 3. .. which are empty
             empty = inputs.filter(function() {
                return $(this).val().replace(/\s*/g, '') == '';
             });

             // if there are empty fields, then
             if (empty.length) {
                 // slide down the drawer
                 drawer.slideDown(function()  {     
                     // colored flash effect
                     drawer.css("backgroundColor", "#0078C1");
                     setTimeout(function() { drawer.css("backgroundColor", "#fff"); }, 1000);
                 });
     
                 // add a CSS class name "error" for empty & required fields
                 empty.addClass("error");
     
                 // cancel seeking of the scrollable by returning false
                 return false;
             // everything is good
             } else {
                 // hide the drawer
                 drawer.slideUp();
             }
        }

        // post data
        if (i >= pageCount) {
            //TODO save using jQuery
            $('#donationForm').submit();
            //TODO if third party payment redirect 
        }

        // on last page replace "confirm" with "donate X CHF"
        if (i >= (pageCount - 1)) {
            // last page
            var amount = $('div#wizard input[name=amount]:radio:checked').val();
            amount = (amount) ? amount.replace('.00', '') : $('div#wizard input#amount-other').val();
            var currency = $('select#currency option:selected').text(),
                text = buttonFinalText.replace('%amount%', amount).replace('%currency%', currency);
            $('div#wizard button#confirmButton').addClass('active').text(text);
        } else {
            $('div#wizard button#confirmButton').removeClass('active').text(buttonConfirmText);
        }

        // update status bar
        $("#status li").removeClass("active").eq(i).addClass("active");
    });

    // if tab is pressed on the next button seek to next page
    root.find("li.last input").keydown(function(e) {
        if (e.keyCode == 9) {
            // no tab allowed on amount page
            if ($(this).attr('id') == 'amount-other') {
                return false;
            }
            // seeks to next tab by executing our validation routine
            api.next();
            e.preventDefault();
        }
    });

    // check radio button and show confirm button
    $('input#amount-other').click(function() {
        $('ul#amounts label').removeClass("active");
        $('ul#amounts input:radio').prop('checked', false);
        $('input#amount-other').parent().addClass("required");
        showConfirmButton();
    });
    $('ul#amounts label').click(function() {
        $('ul#amounts label').removeClass("active");
        $('input#amount-other').val('');
        $('input#amount-other').parent().removeClass("required");
        $(this).addClass("active");
        showConfirmButton();
        api.next();
    });

    // currency stuff
    $('select#currency').change(function() {
        $('span.cur').text($('select#currency option:selected').text());
    });
    $('#currency-link a').click(function() {
        $('#currency-form').slideDown();
    })

    // show div with payment details
    var paymentPanels = $('ul#payment-method > li > label > div');
    $('ul#payment-method > li > input').change(function() {
        paymentPanels.slideUp();
        $(this).next().children('div').slideDown();
        $('ul#payment-method #payment-required').val('1');
        return false;
    });
});

function showConfirmButton() {
    $('#confirmButton').fadeIn();
}