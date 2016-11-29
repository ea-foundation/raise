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
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Load settings
        $settings = json_decode(get_option('settings'), true);

        // Load default settings
        $customSettings  = plugin_dir_path(__FILE__) . "_parameters.js.php";
        $settingsFile    = file_exists($customSettings) ? $customSettings : $customSettings . '.dist';
        $defaultSettings = file_get_contents($settingsFile);
        $defaultSettings = json_decode(trim(end(explode('?>', $defaultSettings, 2))), true);
        
        if (empty($settings) || count($settings) <= 1) {
            $settings = $defaultSettings;
        }
        ?>
        <div class="wrap">
            <h1>Donation Settings</h1>
            <div id="jsoneditor" style="width: 100%; height: 400px;"></div>
            <form id="donation-setting-form" method="post" action="options.php">
                <?php
                    settings_fields('eas-donation-settings-group');
                    do_settings_sections('eas-donation-settings-group');
                ?>
                <input type="hidden" name="settings" value="">
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes" />
                    <input type="reset" class="button" value="Reset" />
                </p>
                <!-- <?php submit_button(); ?> -->
            </form>
            <script>
                // Create the editor
                var container = document.getElementById("jsoneditor");
                var options = {};
                var editor = new JSONEditor(container, options);
                editor.set(<?php echo json_encode($settings) ?>);

                // Stringify editor JSON and put it into hidden form field before submitting the form
                jQuery('#donation-setting-form').submit(function() {
                    // Sringify JSON and save it
                    var json = JSON.stringify(editor.get());
                    jQuery("input[name=settings]").val(json);
                });

                // Show default settings on reset
                jQuery("input[type=reset]").click(function() {
                    editor.set(<?php echo json_encode($defaultSettings) ?>);
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
    }
}

if( is_admin() )
    $my_settings_page = new EasDonationProcessorOptionsPage();
