<?php if (!defined('ABSPATH')) exit;

class RaiseOptionsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action('admin_menu', array( $this, 'add_plugin_page'));
        add_action('admin_init', array( $this, 'page_init'));
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'Raise', 
            'Raise', 
            'manage_options', 
            'raise-donation-settings',
            array($this, 'create_admin_page')
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Update settings if necessary
        raise_update_settings();

        // Default colors
        $defaultColors = array(
            'button_color_background'               => '#0078c1',
            'button_color_background_hover'         => '#1297c9',
            'button_color_background_active'        => '#5cb85c',
            'button_color_border'                   => '#0088bb',
            'button_color_border_hover'             => '#1190c0',
            'button_color_border_active'            => '#4cae4c',
            'button_color_text'                     => '#ffffff',
            'button_color_text_hover'               => '#ffffff',
            'button_color_text_active'              => '#ffffff',
            'widget_color_text_active'              => '#0078c1',
            'confirm_button_color_background'       => '#5cb85c',
            'confirm_button_color_background_hover' => '#449d44',
            'confirm_button_color_border'           => '#4cae4c',
            'confirm_button_color_border_hover'     => '#398439',
            'confirm_button_color_text'             => '#ffffff',
            'confirm_button_color_text_hover'       => '#ffffff',
        );

        // Load settings
        $settings     = json_decode(get_option('raise_settings'), true);
        $defaultLogo  = plugin_dir_url(__FILE__) . 'images/logo.png';
        $logo         = get_option('raise_logo', $defaultLogo);
        $version      = get_option('raise_version');

        // Load merged settings
        if (function_exists('raise_config')) {
            if ($externalSettings = raise_config()) {
                // Merge
                $mergedSettings = raise_array_replace_recursive($externalSettings, $settings);
            } else {
                $mergedSettings = array("Error" => "Invalid JSON");
            }
        } else {
            $mergedSettings = $settings;
        }

        // Resolve inheritance
        if (isset($mergedSettings['forms']) && is_array($mergedSettings['forms'])) {
            $forms     = $mergedSettings['forms'];
            $formNames = array_keys($forms);
            $mergedSettings['forms'] = array_map(function($formName) use ($forms) {
                $mergedForm = raise_rec_load_settings($formName, $forms);
                unset($mergedForm['inherits']);
                return $mergedForm;
            }, array_combine($formNames, $formNames));
        }
        
        // Button background color
        $buttonBackgroundColor       = get_option('raise_button_color_background', $defaultColors['button_color_background']);
        $buttonBackgroundColorHover  = get_option('raise_button_color_background_hover', $defaultColors['button_color_background_hover']);
        $buttonBackgroundColorActive = get_option('raise_button_color_background_active', $defaultColors['button_color_background_active']);

        // Button border color
        $buttonBorderColor       = get_option('raise_button_color_border', $defaultColors['button_color_border']);
        $buttonBorderColorHover  = get_option('raise_button_color_border_hover', $defaultColors['button_color_border_hover']);
        $buttonBorderColorActive = get_option('raise_button_color_border_active', $defaultColors['button_color_border_active']);

        // Button text color
        $buttonTextColor       = get_option('raise_button_color_text', $defaultColors['button_color_text']);
        $buttonTextColorHover  = get_option('raise_button_color_text_hover', $defaultColors['button_color_text_hover']);
        $buttonTextColorActive = get_option('raise_button_color_text_active', $defaultColors['button_color_text_active']);

        // Widget text color
        $widgetTextColorActive  = get_option('raise_widget_color_text_active', $defaultColors['widget_color_text_active']);

        // Confirm button colors
        $confirmButtonBackgroundColor      = get_option('raise_confirm_button_color_background', $defaultColors['confirm_button_color_background']);
        $confirmButtonBackgroundColorHover = get_option('raise_confirm_button_color_background_hover', $defaultColors['confirm_button_color_background_hover']);
        $confirmButtonBorderColor          = get_option('raise_confirm_button_color_border', $defaultColors['confirm_button_color_border']);
        $confirmButtonBorderColorHover     = get_option('raise_confirm_button_color_border_hover', $defaultColors['confirm_button_color_border_hover']);
        $confirmButtonTextColor            = get_option('raise_confirm_button_color_text', $defaultColors['confirm_button_color_text']);
        $confirmButtonTextColorHover       = get_option('raise_confirm_button_color_text_hover', $defaultColors['confirm_button_color_text_hover']);
        
        $unsavedSettingsMessage = '';
        if (empty($settings) || count($settings) <= 1) {
            // Load default settings
            $customSettings         = plugin_dir_path(__FILE__) . "_parameters.js.php";
            $settingsFile           = file_exists($customSettings) ? $customSettings : $customSettings . '.dist';
            $settingsFileContents   = file_get_contents($settingsFile);
            $settings               = json_decode(trim(end(explode('?>', $settingsFileContents, 2))), true);
            $unsavedSettingsMessage = '<p><strong>Configure settings and save.</strong></p>';
        }
        ?>
        <div id="raise-options" class="wrap">
            <h1>Raise - Donation Plugin</h1>
            <p>Version: <?php echo esc_html($version) ?></p>
            <?php echo $unsavedSettingsMessage ?>
            <div id="tabs">
                <ul><li><a href="#jsoneditor">Local settings</a></li><li><a href="#merged-settings">Merged settings</a></li></ul>
                <div id="jsoneditor"></div>
                <div id="merged-settings"></div>
            </div>
            <form id="donation_setting_form" method="post" action="options.php">
                <?php
                    settings_fields('raise-donation-settings-group');
                    do_settings_sections('raise-donation-settings-group');
                ?>
                <input type="hidden" name="raise_settings" value="">
                <input type="hidden" name="raise_logo" value="<?php echo $logo ?>">
                <h2>Button colors</h2>
                <div class="color-selection">
                    <p style="font-weight: bold">Regular</p>
                    <div>
                        <label for="button_color_background">Background:</label>
                        <input type="color" id="button_color_background" name="raise_button_color_background" value="<?= $buttonBackgroundColor ?>">
                    </div>
                    <div>
                        <label for="button_color_border">Border:</label>
                        <input type="color" id="button_color_border" name="raise_button_color_border" value="<?= $buttonBorderColor ?>">
                    </div>
                    <div>
                        <label for="button_color_text">Text:</label>
                        <input type="color" id="button_color_text" name="raise_button_color_text" value="<?= $buttonTextColor ?>">
                    </div>
                </div>
                <div class="color-selection">
                    <p style="font-weight: bold">On hover (mouse-over)</p>
                    <div>
                        <label for="button_color_background_hover">Background:</label>
                        <input type="color" id="button_color_background_hover" name="raise_button_color_background_hover" value="<?= $buttonBackgroundColorHover ?>">
                    </div>
                    <div>
                        <label for="button_color_border_hover">Border:</label>
                        <input type="color" id="button_color_border_hover" name="raise_button_color_border_hover" value="<?= $buttonBorderColorHover ?>">
                    </div>
                    <div>
                        <label for="button_color_text_hover">Text:</label>
                        <input type="color" id="button_color_text_hover" name="raise_button_color_text_hover" value="<?= $buttonTextColorHover ?>">
                    </div>
                </div>
                <div class="color-selection">
                    <p style="font-weight: bold">On active (selected)</p>
                    <div>
                        <label for="button_color_background_active">Background:</label>
                        <input type="color" id="button_color_background_active" name="raise_button_color_background_active" value="<?= $buttonBackgroundColorActive ?>">
                    </div>
                    <div>
                        <label for="button_color_border_active">Border:</label>
                        <input type="color" id="button_color_border_active" name="raise_button_color_border_active" value="<?= $buttonBorderColorActive ?>">
                    </div>
                    <div>
                        <label for="button_color_text_active">Text:</label>
                        <input type="color" id="button_color_text_active" name="raise_button_color_text_active" value="<?= $buttonTextColorActive ?>">
                    </div>
                </div>

                <h2>Text color</h2>
                <div class="color-selection">
                    <p style="font-weight: bold">Frequency and progress bar</p>
                    <div>
                        <label for="widget_color_text_active">Active text:</label>
                        <input type="color" id="widget_color_text_active" name="raise_widget_color_text_active" value="<?= $widgetTextColorActive ?>">
                    </div>
                </div>

                <div id="advanced-color-settings" class="hidden">
                    <h2>Confirm button colors</h2>
                    <div class="color-selection">
                        <p style="font-weight: bold">Regular</p>
                        <div>
                            <label for="confirm_button_color_background">Background:</label>
                            <input type="color" id="confirm_button_color_background" name="raise_confirm_button_color_background" value="<?= $confirmButtonBackgroundColor ?>">
                        </div>
                        <div>
                            <label for="confirm_button_color_border">Border:</label>
                            <input type="color" id="confirm_button_color_border" name="raise_confirm_button_color_border" value="<?= $confirmButtonBorderColor ?>">
                        </div>
                        <div>
                            <label for="confirm_button_color_text">Text:</label>
                            <input type="color" id="confirm_button_color_text" name="raise_confirm_button_color_text" value="<?= $confirmButtonTextColor ?>">
                        </div>
                    </div>
                    <div class="color-selection">
                        <p style="font-weight: bold">On hover (mouse-over)</p>
                        <div>
                            <label for="confirm_button_color_background_hover">Background:</label>
                            <input type="color" id="confirm_button_color_background_hover" name="raise_confirm_button_color_background_hover" value="<?= $confirmButtonBackgroundColorHover ?>">
                        </div>
                        <div>
                            <label for="confirm_button_color_border_hover">Border:</label>
                            <input type="color" id="confirm_button_color_border_hover" name="raise_confirm_button_color_border_hover" value="<?= $confirmButtonBorderColorHover ?>">
                        </div>
                        <div>
                            <label for="confirm_button_color_text_hover">Text:</label>
                            <input type="color" id="confirm_button_color_text_hover" name="raise_confirm_button_color_text_hover" value="<?= $confirmButtonTextColorHover ?>">
                        </div>
                    </div>
                </div>

                <div class="donation-settings-block">
                    <a data-expander-target="advanced-color-settings" class="advanced-settings-expander">Show advanced color settings</a>
                </div>

                <div class="donation-settings-block">
                    <button type="button" class="button reset-button-colors">Reset button and text colors</button>
                </div>

                <h2>Stripe/Skrill logo</h2>
                <p>
                    Recommended minimum size: 128x128px<br>
                    <div class="stripe-logo" style="background-image: url('<?php echo $logo ?>')"></div><br>
                    <?php if ($logo != $defaultLogo): ?>
                        <button type="button" class="button reset-stripe-logo">Reset logo</button>
                    <?php endif; ?>
                </p>

                <?php submit_button() ?>
            </form>
            <script>
                // Create the editor
                var container = document.getElementById("jsoneditor");
                var options = {'modes': ['tree', 'code']};
                var editor = new JSONEditor(container, options);
                editor.set(<?php echo json_encode($settings) ?>);

                // Create merged settings
                var mergedContainer = document.getElementById("merged-settings");
                var mergedOptions = {'mode': 'view'};
                var mergedEditor = new JSONEditor(mergedContainer, mergedOptions);
                mergedEditor.set(<?php echo json_encode($mergedSettings) ?>);

                // Make tabs if there are settings from the config plugin
                jQuery("#tabs").tabs({ active: 0 });

                var customUploader;
                var logo   = jQuery('.stripe-logo');
                var target = jQuery('.wrap input[name="raise_logo"]');

                logo.click(function(e) {
                    e.preventDefault();
                    //If the uploader object has already been created, reopen the dialog
                    if (customUploader) {
                        customUploader.open();
                        return;
                    }

                    //Extend the wp.media object
                    customUploader = wp.media.frames.file_frame = wp.media({
                        title: 'Choose Image',
                        button: {
                            text: 'Choose Image'
                        },
                        multiple: false
                    });

                    //When a file is selected, grab the URL and set it as the text field's value
                    customUploader.on('select', function() {
                        attachment = customUploader.state().get('selection').first().toJSON();
                        target.val(attachment.url);
                        logo.css('backgroundImage', "url('" + attachment.url + "')");
                    });

                    //Open the uploader dialog
                    customUploader.open();
                });

                // Stringify editor JSON and put it into hidden form field before submitting the form
                jQuery('#donation_setting_form').submit(function() {
                    // Sringify JSON and save it
                    try {
                        eval('(' + JSON.stringify(editor.get()) + ')');
                    } catch (e) {
                        alert(e);
                        return false;
                    }

                    var json = JSON.stringify(editor.get());
                    jQuery("input[name=raise_settings]").val(json);
                });

                // Reset stripe logo
                jQuery('.reset-stripe-logo').click(function() {
                    var defaultLogo = '<?= $defaultLogo ?>';
                    jQuery('.stripe-logo').css('backgroundImage', "url('" + defaultLogo + "'");
                    jQuery('input[name=raise_logo]').val(defaultLogo);
                });

                // Reset button colors
                jQuery('.reset-button-colors').click(function() {
                    var defaultColors = <?= json_encode($defaultColors) ?>;
                    for (key in defaultColors) {
                        jQuery('input#' + key).val(defaultColors[key]);
                    }
                });

                // Toggle visibility of advanced color settings
                jQuery('a.advanced-settings-expander').click(function() {
                    var advancedColorSettings = jQuery('#' + jQuery(this).attr('data-expander-target'));
                    var linkLabel             = advancedColorSettings.css('display') == 'none' ? jQuery(this).text().replace("Show", "Hide") : jQuery(this).text().replace("Hide", "Show");

                    jQuery(this).text(linkLabel);
                    advancedColorSettings.toggle();
                });
            </script>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting('raise-donation-settings-group', 'raise_settings');
        register_setting('raise-donation-settings-group', 'raise_logo');

        register_setting('raise-donation-settings-group', 'raise_button_color_background');
        register_setting('raise-donation-settings-group', 'raise_button_color_background_hover');
        register_setting('raise-donation-settings-group', 'raise_button_color_background_active');

        register_setting('raise-donation-settings-group', 'raise_button_color_border');
        register_setting('raise-donation-settings-group', 'raise_button_color_border_hover');
        register_setting('raise-donation-settings-group', 'raise_button_color_border_active');

        register_setting('raise-donation-settings-group', 'raise_button_color_text');
        register_setting('raise-donation-settings-group', 'raise_button_color_text_hover');
        register_setting('raise-donation-settings-group', 'raise_button_color_text_active');

        register_setting('raise-donation-settings-group', 'raise_widget_color_text_active');

        register_setting('raise-donation-settings-group', 'raise_confirm_button_color_background');
        register_setting('raise-donation-settings-group', 'raise_confirm_button_color_background_hover');
        register_setting('raise-donation-settings-group', 'raise_confirm_button_color_border');
        register_setting('raise-donation-settings-group', 'raise_confirm_button_color_border_hover');
        register_setting('raise-donation-settings-group', 'raise_confirm_button_color_text');
        register_setting('raise-donation-settings-group', 'raise_confirm_button_color_text_hover');

        register_setting('raise-donation-settings-group', 'raise_tax_deduction_expose');
        register_setting('raise-donation-settings-group', 'raise_tax_deduction_secret');
        register_setting('raise-donation-settings-group', 'raise_tax_deduction_cache_ttl');
        register_setting('raise-donation-settings-group', 'raise_tax_deduction_last_refreshed');
        register_setting('raise-donation-settings-group', 'raise_tax_deduction_remote_url');
        register_setting('raise-donation-settings-group', 'raise_tax_deduction_remote_form_name');
        register_setting('raise-donation-settings-group', 'raise_tax_deduction_remote_settings');
    }
}

if (is_admin()) {
    $my_settings_page = new RaiseOptionsPage();
}
