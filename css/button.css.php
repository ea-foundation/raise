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
?>
ul#amounts input[type=text] {
    text-overflow: ellipsis;
    display: inline-block;
    height: 46px;
    font-size: inherit;
    border-top-right-radius: 6px;
    border-bottom-right-radius: 6px;
    backface-visibility: hidden;
    border: 1px solid <?= $borderColor ?>;
    background: <?= $backgroundColor ?>;
    backface-visibility: hidden;
    font-weight: 700;
    padding: 5px 15px;
    outline: 0;
}

/* Placeholder color */
ul#amounts input[type=text]::-webkit-input-placeholder { /* Chrome/Opera/Safari */
  color: <?= $textColor ?>;
}
ul#amounts input[type=text]::-moz-placeholder { /* Firefox 19+ */
  color: <?= $textColor ?>;
}
ul#amounts input[type=text]:-ms-input-placeholder { /* IE 10+ */
  color: <?= $textColor ?>;
}
ul#amounts input[type=text]:-moz-placeholder { /* Firefox 18- */
  color: <?= $textColor ?>;
}

ul#amounts input[type=text].active {
    background: <?= $backgroundColorActive ?>;
    color: <?= $textColorActive ?>;
    border: 1px solid <?= $borderColorActive ?>;
}

ul#amounts label:hover {
    -webkit-transition: background .1s linear;
    -moz-transition: background .1s linear;
    transition: background .1s linear;
    background: <?= $backgroundColorHover ?>;
    color: <?= $textColorHover ?>;
    border: 1px solid <?= $borderColorHover ?>;
}

ul#amounts label {
    background: <?= $backgroundColor ?>;
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

ul#amounts span.input-group-addon {
    background: <?= $backgroundColor ?>;
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

ul#amounts span.input-group-addon.active {
    background: <?= $backgroundColorActive ?>;
    border: 1px solid <?= $borderColorActive ?>;
    border-right: 0;
    color: <?= $textColorActive ?>;
}

ul#amounts label.active {
    background: <?= $backgroundColorActive ?>;
    border: 1px solid <?= $borderColorActive ?>;
    color: <?= $textColorActive ?>;
}

ul#frequency label.active {
    color: <?= $backgroundColor ?>;
    border-bottom: 2px solid <?= $backgroundColor ?>;
}

#progress li.active {
    text-shadow: 0px 1px 0px rgba(255, 255, 255, 0.8);
    color: <?= $backgroundColor ?>;
}

#progress li.active span {
    background: <?= $backgroundColor ?>;
    color: <?= $textColor ?>;
    font-weight: bold;
}