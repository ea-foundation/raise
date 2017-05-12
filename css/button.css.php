<?php
    header('Content-type: text/css');
    include_once("../../../../wp-load.php");

    // Button background color
    $backgroundColor       = get_option('button-color-background', '#0078c1');
    $backgroundColorHover  = get_option('button-color-background-hover', '#1297c9');
    $backgroundColorActive = get_option('button-color-background-active', '#5cb85c');

    // Button border color
    $borderColor       = get_option('button-color-border', '#0088bb');
    $borderColorHover  = get_option('button-color-border-hover', '#1190c0');
    $borderColorActive = get_option('button-color-border-active', '#4cae4c');

    // Button text color
    $textColor         = get_option('button-color-text', '#ffffff');
    $textColorHover    = get_option('button-color-text-hover', '#ffffff');
    $textColorActive   = get_option('button-color-text-active', '#ffffff');

    // Widget text color
    $widgetColorActive   = get_option('widget-color-text-active', '#0078c1');
    $widgetColorInactive = get_option('widget-color-text-inactive', '#999999');

    // Confirm button color
    $confirmBackgroundColor      = get_option('confirm-button-color-background', '#5cb85c');
    $confirmBackgroundColorHover = get_option('confirm-button-color-background-hover', '#449d44');
    $confirmBorderColor          = get_option('confirm-button-color-border', '#4cae4c');
    $confirmBorderColorHover     = get_option('confirm-button-color-border-hover', '#398439');
    $confirmTextColor            = get_option('confirm-button-color-text', '#ffffff');
    $confirmTextColorHover       = get_option('confirm-button-color-text-hover', '#ffffff');
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

ul#amounts input[type=text] {
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

ul#amounts input[type=text].active {
    background-color: <?= $backgroundColorActive ?>;
    border-color: <?= $borderColorActive ?>;
    color: <?= $textColorActive ?>;
}

/* Amount other placeholder color */

ul#amounts input[type=text]::-webkit-input-placeholder { /* Chrome/Opera/Safari */
    color: <?= $textColor ?>;
}
ul#amounts input[type=text]::-moz-placeholder { /* Firefox 19+ */
    color: <?= $textColor ?>;
}
ul#amounts input[type=text]:-ms-input-placeholder { /* IE 10+ */
    color: <?= $textColor ?>;
}
ul#amounts input[type=text]:-moz-placeholder { /* Firefox 18+ */
    color: <?= $textColor ?>;
}

/* Frequency */

ul#frequency label {
    color: <?= $widgetColorInactive ?>;
    text-transform: uppercase;
    cursor: pointer;
    background: none;
    border: none;
    font-weight: 700;
    margin-top: 10px;
    padding: 0;
    outline: 0;
}

ul#frequency label.active {
    color: <?= $widgetColorActive ?>;
    border-bottom: 2px solid <?= $widgetColorActive ?>;
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

#wizard button.confirm:after {
    content: " »";
}
