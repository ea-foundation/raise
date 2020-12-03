<?php
    header('Content-type: text/css');
    include_once("../../../../../wp-load.php");

    // Button background color
    $backgroundColor       = get_option('raise_button_color_background', '#0078c1');
    $backgroundColorHover  = get_option('raise_button_color_background_hover', '#1297c9');
    $backgroundColorActive = get_option('raise_button_color_background_active', '#5cb85c');

    // Button border color
    $borderColor       = get_option('raise_button_color_border', '#0088bb');
    $borderColorHover  = get_option('raise_button_color_border_hover', '#1190c0');
    $borderColorActive = get_option('raise_button_color_border_active', '#4cae4c');

    // Button text color
    $textColor         = get_option('raise_button_color_text', '#ffffff');
    $textColorHover    = get_option('raise_button_color_text_hover', '#ffffff');
    $textColorActive   = get_option('raise_button_color_text_active', '#ffffff');

    // Widget text color
    $widgetColorActive   = get_option('raise_widget_color_text_active', '#0078c1');

    // Confirm button color
    $confirmBackgroundColor      = get_option('raise_confirm_button_color_background', '#5cb85c');
    $confirmBackgroundColorHover = get_option('raise_confirm_button_color_background_hover', '#449d44');
    $confirmBorderColor          = get_option('raise_confirm_button_color_border', '#4cae4c');
    $confirmBorderColorHover     = get_option('raise_confirm_button_color_border_hover', '#398439');
    $confirmTextColor            = get_option('raise_confirm_button_color_text', '#ffffff');
    $confirmTextColorHover       = get_option('raise_confirm_button_color_text_hover', '#ffffff');
?>
ul#amounts label {
    background-color: <?= $backgroundColor ?>;
    border: 1px solid <?= $borderColor ?>;
    color: <?= $textColor ?>;
    border-radius: 6px;
    height: 46px;
    padding: 5px 0;
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: 700;
    cursor: pointer;
    outline: 0;
    backface-visibility: hidden;
}

ul#amounts label:hover {
    background-color: <?= $backgroundColorHover ?>;
    border-color: <?= $borderColorHover ?>;
    color: <?= $textColorHover ?>;
    -webkit-transition: background .1s linear;
    -moz-transition: background .1s linear;
    transition: background .1s linear;
}

ul#amounts label.active {
    background-color: <?= $backgroundColorActive ?>;
    border-color: <?= $borderColorActive ?>;
    color: <?= $textColorActive ?>;
}

/* Amount other */

ul#amounts span.input-group-addon {
    background-color: <?= $backgroundColor ?>;
    border: 1px solid <?= $borderColor ?>;
    border-right: 0;
    color: <?= $textColor ?>;
    backface-visibility: hidden;
    cursor: pointer;
    font-weight: 700;
    padding: 5px 16px;
    font-size: 100%;
    outline: 0;
    border-top-left-radius: 6px;
    border-bottom-left-radius: 6px;
}

ul#amounts input[type=number] {
    color: <?= $textColor ?>;
    text-overflow: ellipsis;
    display: inline-block;
    height: 46px;
    font-size: inherit;
    border-top-right-radius: 6px;
    border-bottom-right-radius: 6px;
    backface-visibility: hidden;
    border: 1px solid <?= $borderColor ?>;
    background-color: <?= $backgroundColor ?>;
    backface-visibility: hidden;
    font-weight: 700;
    padding: 5px 15px;
    outline: 0;
}

ul#amounts span.input-group-addon.active {
    background-color: <?= $backgroundColorActive ?>;
    border-color: <?= $borderColorActive ?>;
    color: <?= $textColorActive ?>;
    border-right: 0;
}

ul#amounts input[type=number].active {
    background-color: <?= $backgroundColorActive ?>;
    border-color: <?= $borderColorActive ?>;
    color: <?= $textColorActive ?>;
}

/* Amount other placeholder color */

ul#amounts input[type=number]::-webkit-input-placeholder { /* Chrome/Opera/Safari */
    color: <?= $textColor ?>;
}
ul#amounts input[type=number]::-moz-placeholder { /* Firefox 19+ */
    color: <?= $textColor ?>;
}
ul#amounts input[type=number]:-ms-input-placeholder { /* IE 10+ */
    color: <?= $textColor ?>;
}
ul#amounts input[type=number]:-moz-placeholder { /* Firefox 18+ */
    color: <?= $textColor ?>;
}

/* Frequency */

.monthly-donation-teaser {
    position: relative;
    top: -20px;
    padding: 4px 10px;
    color: white;
    background-color: <?= $widgetColorActive ?>;
}

ul#frequency label.active {
    color: <?= $widgetColorActive ?>;
    border-bottom: 2px solid <?= $widgetColorActive ?>;
}

.average-amounts {
    color: <?= $widgetColorActive ?>;
}

/* Progress bar */

#progress li.active {
    text-shadow: 0px 1px 0px rgba(255, 255, 255, 0.8);
    color: <?= $widgetColorActive ?>;
}

#progress li.active span {
    background-color: <?= $backgroundColor ?>;
    border: 1px solid <?= $borderColor ?>;
    color: <?= $textColor ?>;
    font-weight: bold;
}

/* Confirm button */

#wizard button.confirm {
    background-color: <?= $confirmBackgroundColor ?>;
    border-color: <?= $confirmBorderColor ?>;
    color: <?= $confirmTextColor ?>;
    width: 100% !important;
    text-transform: none;
    letter-spacing: 1px;
}

#wizard button.confirm:hover {
    background-color: <?= $confirmBackgroundColorHover ?>;
    border-color: <?= $confirmBorderColorHover ?>;
    color: <?= $confirmTextColorHover ?>;
}

#wizard button.donation-continue:after {
    content: " »";
}
