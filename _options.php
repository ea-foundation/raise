<?php if (!defined('ABSPATH')) exit;

class EasDonationProcessorOptionsPage
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
            'EAS Donation Processor', 
            'Donation Plugin', 
            'manage_options', 
            'eas-donation-settings',
            array($this, 'create_admin_page')
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Update settings if necessary
        updateSettings();

        // Default colors
        $defaultColors = array(
            'button-color-background'               => '#0078c1',
            'button-color-background-hover'         => '#1297c9',
            'button-color-background-active'        => '#5cb85c',
            'button-color-border'                   => '#0088bb',
            'button-color-border-hover'             => '#1190c0',
            'button-color-border-active'            => '#4cae4c',
            'button-color-text'                     => '#ffffff',
            'button-color-text-hover'               => '#ffffff',
            'button-color-text-active'              => '#ffffff',
            'widget-color-text-active'              => '#0078c1',
            'widget-color-text-inactive'            => '#999999',
            'confirm-button-color-background'       => '#5cb85c',
            'confirm-button-color-background-hover' => '#449d44',
            'confirm-button-color-border'           => '#4cae4c',
            'confirm-button-color-border-hover'     => '#398439',
            'confirm-button-color-text'             => '#ffffff',
            'confirm-button-color-text-hover'       => '#ffffff',
        );

        // Load settings
        $settings          = json_decode(get_option('settings'), true);
        $defaultLogo       = plugin_dir_url(__FILE__) . 'images/logo.png';
        $logo              = get_option('logo', $defaultLogo);
        $version           = get_option('version');
        
        // Button background color
        $buttonBackgroundColor       = get_option('button-color-background', $defaultColors['button-color-background']);
        $buttonBackgroundColorHover  = get_option('button-color-background-hover', $defaultColors['button-color-background-hover']);
        $buttonBackgroundColorActive = get_option('button-color-background-active', $defaultColors['button-color-background-active']);

        // Button border color
        $buttonBorderColor       = get_option('button-color-border', $defaultColors['button-color-border']);
        $buttonBorderColorHover  = get_option('button-color-border-hover', $defaultColors['button-color-border-hover']);
        $buttonBorderColorActive = get_option('button-color-border-active', $defaultColors['button-color-border-active']);

        // Button text color
        $buttonTextColor       = get_option('button-color-text', $defaultColors['button-color-text']);
        $buttonTextColorHover  = get_option('button-color-text-hover', $defaultColors['button-color-text-hover']);
        $buttonTextColorActive = get_option('button-color-text-active', $defaultColors['button-color-text-active']);

        // Widget text color
        $widgetTextColorActive  = get_option('widget-color-text-active', $defaultColors['widget-color-text-active']);
        $widgetTextColorInactive = get_option('widget-color-text-inactive', $defaultColors['widget-color-text-inactive']);

        // Confirm button colors
        $confirmButtonBackgroundColor      = get_option('confirm-button-color-background', $defaultColors['confirm-button-color-background']);
        $confirmButtonBackgroundColorHover = get_option('confirm-button-color-background-hover', $defaultColors['confirm-button-color-background-hover']);
        $confirmButtonBorderColor          = get_option('confirm-button-color-border', $defaultColors['confirm-button-color-border']);
        $confirmButtonBorderColorHover     = get_option('confirm-button-color-border-hover', $defaultColors['confirm-button-color-border-hover']);
        $confirmButtonTextColor            = get_option('confirm-button-color-text', $defaultColors['confirm-button-color-text']);
        $confirmButtonTextColorHover       = get_option('confirm-button-color-text-hover', $defaultColors['confirm-button-color-text-hover']);
        
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
        <div class="wrap">
            <h1>Donation Plugin</h1>
            <p>Version: <?php echo esc_html($version) ?></p>
            <?php echo $unsavedSettingsMessage ?>
            <div id="jsoneditor" style="width: 100%; height: 400px;"></div>
            <form id="donation-setting-form" method="post" action="options.php">
                <?php
                    settings_fields('eas-donation-settings-group');
                    do_settings_sections('eas-donation-settings-group');
                ?>
                <input type="hidden" name="settings" value="">
                <input type="hidden" name="logo" value="<?php echo $logo ?>">
                <h2>Button colors</h2>
                <div class="color-selection">
                    <div>
                        <label for="button-color-background">Background:</label>
                        <input type="color" id="button-color-background" name="button-color-background" value="<?= $buttonBackgroundColor ?>">
                    </div>
                    <div>
                        <label for="button-color-border">Border:</label>
                        <input type="color" id="button-color-border" name="button-color-border" value="<?= $buttonBorderColor ?>">
                    </div>
                    <div>
                        <label for="button-color-text">Text:</label>
                        <input type="color" id="button-color-text" name="button-color-text" value="<?= $buttonTextColor ?>">
                    </div>
                </div>
                <p style="font-weight: bold">On hover (mouse-over):</p>
                <div class="color-selection">
                    <div>
                        <label for="button-color-background-hover">Background:</label>
                        <input type="color" id="button-color-background-hover" name="button-color-background-hover" value="<?= $buttonBackgroundColorHover ?>">
                    </div>
                    <div>
                        <label for="button-color-border-hover">Border:</label>
                        <input type="color" id="button-color-border-hover" name="button-color-border-hover" value="<?= $buttonBorderColorHover ?>">
                    </div>
                    <div>
                        <label for="button-color-text-hover">Text:</label>
                        <input type="color" id="button-color-text-hover" name="button-color-text-hover" value="<?= $buttonTextColorHover ?>">
                    </div>
                </div>
                <p style="font-weight: bold">On active (selected):</p>
                <div class="color-selection">
                    <div>
                        <label for="button-color-background-active">Background:</label>
                        <input type="color" id="button-color-background-active" name="button-color-background-active" value="<?= $buttonBackgroundColorActive ?>">
                    </div>
                    <div>
                        <label for="button-color-border-active">Border:</label>
                        <input type="color" id="button-color-border-active" name="button-color-border-active" value="<?= $buttonBorderColorActive ?>">
                    </div>
                    <div>
                        <label for="button-color-text-active">Text:</label>
                        <input type="color" id="button-color-text-active" name="button-color-text-active" value="<?= $buttonTextColorActive ?>">
                    </div>
                </div>

                <h2>Text color</h2>
                <p style="font-weight: bold">Frequency and progress bar:</p>
                <div class="color-selection">
                    <div>
                        <label for="widget-color-text-active">Active text:</label>
                        <input type="color" id="widget-color-text-active" name="widget-color-text-active" value="<?= $widgetTextColorActive ?>">
                    </div>
                    <div>
                        <label for="widget-color-text-inactive">Inactive text:</label>
                        <input type="color" id="widget-color-text-inactive" name="widget-color-text-inactive" value="<?= $widgetTextColorInactive ?>">
                        (inactive frequency)
                    </div>
                </div>

                <div id="advanced-color-settings" class="hidden">
                    <h2>Confirm button colors</h2>
                    <div class="color-selection">
                        <div>
                            <label for="confirm-button-color-background">Background:</label>
                            <input type="color" id="confirm-button-color-background" name="confirm-button-color-background" value="<?= $confirmButtonBackgroundColor ?>">
                        </div>
                        <div>
                            <label for="confirm-button-color-border">Border:</label>
                            <input type="color" id="confirm-button-color-border" name="confirm-button-color-border" value="<?= $confirmButtonBorderColor ?>">
                        </div>
                        <div>
                            <label for="confirm-button-color-text">Text:</label>
                            <input type="color" id="confirm-button-color-text" name="confirm-button-color-text" value="<?= $confirmButtonTextColor ?>">
                        </div>
                    </div>
                    <p style="font-weight: bold">On hover (mouse-over):</p>
                    <div class="color-selection">
                        <div>
                            <label for="confirm-button-color-background-hover">Background:</label>
                            <input type="color" id="confirm-button-color-background-hover" name="confirm-button-color-background-hover" value="<?= $confirmButtonBackgroundColorHover ?>">
                        </div>
                        <div>
                            <label for="confirm-button-color-border-hover">Border:</label>
                            <input type="color" id="confirm-button-color-border-hover" name="confirm-button-color-border-hover" value="<?= $confirmButtonBorderColorHover ?>">
                        </div>
                        <div>
                            <label for="confirm-button-color-text-hover">Text:</label>
                            <input type="color" id="confirm-button-color-text-hover" name="confirm-button-color-text-hover" value="<?= $confirmButtonTextColorHover ?>">
                        </div>
                    </div>
                </div>

                <div class="donation-settings-block">
                    <a id="advanced-color-settings-expander" class="advanced-settings-expander">Show advanced color settings</a>
                </div>

                <div class="donation-settings-block">
                    <button type="button" class="button reset-button-colors">Reset button colors</button>
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

                // Stringify editor JSON and put it into hidden form field before submitting the form
                jQuery('#donation-setting-form').submit(function() {
                    // Sringify JSON and save it
                    var json = JSON.stringify(editor.get());
                    jQuery("input[name=settings]").val(json);
                });

                // Reset stripe logo
                jQuery('.reset-stripe-logo').click(function() {
                    var defaultLogo = 'https://dev.ea-stiftung.org/wp-content/plugins/eas-donation-processor/images/logo.png';
                    jQuery('.stripe-logo').css('backgroundImage', "url('" + defaultLogo + "'");
                    jQuery('input[name=logo]').val(defaultLogo);
                });

                // Reset button colors
                jQuery('.reset-button-colors').click(function() {
                    var defaultColors = <?= json_encode($defaultColors) ?>;
                    for (key in defaultColors) {
                        jQuery('input#' + key).val(defaultColors[key]);
                    }
                });

                // Toggle visibility of advanced color settings
                jQuery('a#advanced-color-settings-expander').click(function() {
                    var advancedColorSettings = jQuery('div#advanced-color-settings');
                    var linkLabel             = advancedColorSettings.css('display') == 'none' ? "Hide advanced color settings" : "Show advanced color settings" ;

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
        register_setting('eas-donation-settings-group', 'settings');
        register_setting('eas-donation-settings-group', 'logo');

        register_setting('eas-donation-settings-group', 'button-color-background');
        register_setting('eas-donation-settings-group', 'button-color-background-hover');
        register_setting('eas-donation-settings-group', 'button-color-background-active');

        register_setting('eas-donation-settings-group', 'button-color-border');
        register_setting('eas-donation-settings-group', 'button-color-border-hover');
        register_setting('eas-donation-settings-group', 'button-color-border-active');

        register_setting('eas-donation-settings-group', 'button-color-text');
        register_setting('eas-donation-settings-group', 'button-color-text-hover');
        register_setting('eas-donation-settings-group', 'button-color-text-active');

        register_setting('eas-donation-settings-group', 'widget-color-text-active');
        register_setting('eas-donation-settings-group', 'widget-color-text-inactive');

        register_setting('eas-donation-settings-group', 'confirm-button-color-background');
        register_setting('eas-donation-settings-group', 'confirm-button-color-background-hover');
        register_setting('eas-donation-settings-group', 'confirm-button-color-border');
        register_setting('eas-donation-settings-group', 'confirm-button-color-border-hover');
        register_setting('eas-donation-settings-group', 'confirm-button-color-text');
        register_setting('eas-donation-settings-group', 'confirm-button-color-text-hover');
    }
}

if (is_admin()) {
    $my_settings_page = new EasDonationProcessorOptionsPage();
}
