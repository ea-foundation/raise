<?php if (!defined('ABSPATH')) exit;

// Add updates to the settings at the bottom

/**
 * Update settings
 */
function raise_update_settings()
{
    $pluginVersion = eas_get_plugin_version();

    // Get settings
    if (!($settingsVersion = get_option('raise_version')) || !($settings = json_decode(get_option('raise_settings'), true))) {
        // Backward compatibility
        $settingsVersion = get_option('version');
        $settings        = json_decode(get_option('settings'), true);
    }
    
    if (empty($settingsVersion)) {
        if (empty($settings)) {
            // Fresh install, version not set yet, do it now
            $settingsVersion = $pluginVersion;
        } else {
            // Previous version didn't have version settings (< 0.1.24)
            $settingsVersion = '0.1.23';
        }
        update_option('raise_version', $settingsVersion);
    }

    if ($pluginVersion == $settingsVersion || empty($settings)) {
        // Everything up-to-date or no settings to be updated
        return;
    }

    /**
     * Date:   2017-01-06
     * Author: Naoki Peter
     */
    if (version_compare($settingsVersion, '0.1.24', '<')) {
        // Rename web_hook node to webhook
        foreach (array_keys($settings['forms']) as $formName) {
            if (isset($settings['forms'][$formName]['web_hook'])) {
                $settings['forms'][$formName]['webhook'] = $settings['forms'][$formName]['web_hook'];
                unset($settings['forms'][$formName]['web_hook']);
            }
        }

        // Save changes
        update_option('raise_settings', json_encode($settings));
        update_option('raise_version', '0.1.24');
    }

    /**
     * Date:   2017-01-13
     * Author: Naoki Peter
     */
    if (version_compare($settingsVersion, '0.1.29', '<')) {
        // HookPress plugin is obsolete now. Move HookPress endpoints to plugin settings.
        $hookPress = ABSPATH . 'wp-content/plugins/hookpress/hookpress.php';
        if (file_exists($hookPress)) {
            require_once $hookPress;
        }

        if (is_plugin_active('hookpress/hookpress.php') && function_exists('hookpress_get_hooks')) {
            $hooks = hookpress_get_hooks();

            // Replace webhook names with actual webhook endpoints (e.g. Zapier URLs)
            foreach (array_keys($settings['forms']) as $formName) {
                // Logging
                if (isset($settings['forms'][$formName]['webhook']['logging'])) {
                    $hookNames    = $settings['forms'][$formName]['webhook']['logging'];
                    $loggingHooks = array_values(array_filter($hooks, function ($hook) use ($hookNames) {
                        return strpos($hook['hook'], 'eas_donation_logging_') === 0
                               && in_array(end(explode('_', $hook['hook'])), $hookNames)
                               && $hook['enabled'];
                    }));
                    $settings['forms'][$formName]['webhook']['logging'] = array_map(function ($hook) {
                        return $hook['url'];
                    }, $loggingHooks);
                }

                // Mailing lists
                if (isset($settings['forms'][$formName]['webhook']['mailing_list'])) {
                    $hookNames        = $settings['forms'][$formName]['webhook']['mailing_list'];
                    $mailingListHooks = array_values(array_filter($hooks, function ($hook) use ($hookNames) {
                        return strpos($hook['hook'], 'eas_donation_mailinglist_') === 0
                               && in_array(end(explode('_', $hook['hook'])), $hookNames)
                               && $hook['enabled'];
                    }));
                    $settings['forms'][$formName]['webhook']['mailing_list'] = array_map(function ($hook) {
                        return $hook['url'];
                    }, $mailingListHooks);
                }
            }

            // Save changes
            update_option('raise_settings', json_encode($settings));
        }
        update_option('raise_version', '0.1.29');
    }

    /**
     * Date:   2017-02-03
     * Author: Naoki Peter
     */
    if (version_compare($settingsVersion, '0.3.2', '<')) {
        // Save checkbox labels for tax receipt and mailing list signup to default settings
        $taxReceiptLabels  = array(
            "en" => "I need a tax receipt for Germany, Switzerland, the Netherlands, or the United States",
            "de" => "Ich benötige eine Steuerbescheinigung für Deutschland, die Schweiz, die Niederlanden oder die USA",
        );
        $mailingListLabels = array(
            "en" => "Subscribe me to monthly EA updates",
            "de" => "Monatliche EA-Updates abonnieren",
        );

        // Save to settings
        $settings['forms']['default']['payment']['labels'] = array(
            "tax_receipt"  => $taxReceiptLabels,
            "mailing_list" => $mailingListLabels,
        );

        // Save changes
        update_option('raise_settings', json_encode($settings));
        update_option('raise_version', '0.3.2');
    }

    /**
     * Date:   2017-04-28
     * Author: Naoki Peter
     */
    if (version_compare($settingsVersion, '0.5.0', '<')) {
        // Add sandbox/live layer to webhook/logging and webhook/mailing_list
        foreach (array_keys($settings['forms']) as $formName) {
            // Logging
            if (!empty($settings['forms'][$formName]['webhook']['logging']) && !isset($settings['forms'][$formName]['webhook']['logging']['live'])) {
                $settings['forms'][$formName]['webhook']['logging'] = array(
                    "live"    => $settings['forms'][$formName]['webhook']['logging'],
                    "sandbox" => $settings['forms'][$formName]['webhook']['logging'],
                );
            }

            // Mailing lists
            if (!empty($settings['forms'][$formName]['webhook']['mailing_list']) && !isset($settings['forms'][$formName]['webhook']['mailing_list']['live'])) {
                $settings['forms'][$formName]['webhook']['mailing_list'] = array(
                    "live"    => $settings['forms'][$formName]['webhook']['mailing_list'],
                    "sandbox" => $settings['forms'][$formName]['webhook']['mailing_list'],
                );
            }
        }

        // Save changes
        update_option('raise_settings', json_encode($settings));
        update_option('raise_version', '0.5.0');
    }

    /**
     * Date:   2017-05-09
     * Author: Naoki Peter
     */
    if (version_compare($settingsVersion, '0.5.1', '<')) {
        if (!get_option('widget-color-text-active')) {
            // Use background color to ensure backwards compatibility
            $backgroundColor = get_option('button-color-background', '#0078c1');
            update_option('widget-color-text-active', $backgroundColor);
        }

        update_option('raise_version', '0.5.1');
    }

    /**
     * Date:   2017-09-19
     * Author: Naoki Peter
     */
    if (version_compare($settingsVersion, '0.11.3', '<')) {
        foreach ($settings['forms'] as $form => $formSettings) {
            if ($form == 'default') {
                continue;
            }

            // Explicit inheritance from default form
            if (empty($formSettings['inherits'])) {
                $settings['forms'][$form]['inherits'] = 'default';
            }
        }

        // Save changes
        update_option('raise_settings', json_encode($settings));
        update_option('raise_version', '0.11.3');
    }

    /**
     * Date:   2017-09-21
     * Author: Naoki Peter
     */
    if (version_compare($settingsVersion, '0.12.0', '<')) {
        if (isset($settings['forms']['default']['payment']['provider']) &&
            !isset($settings['forms']['default']['payment']['provider']['banktransfer'])) {
            // Bank transfer option has to be explicit now
            $settings['forms']['default']['payment']['provider']['banktransfer'] = [];
        }

        // Save changes
        update_option('raise_settings', json_encode($settings));
        update_option('raise_version', '0.12.0');
    }

    /**
     * Date:   2017-10-04
     * Author: Naoki Peter
     */
    if (version_compare($settingsVersion, '0.13.2', '<')) {
        // Move settings
        update_option('raise_logo', get_option('logo'));
        update_option('raise_button_color_background', get_option('button-color-background'));
        update_option('raise_button_color_background_hover', get_option('button-color-background-hover'));
        update_option('raise_button_color_background_active', get_option('button-color-background-active'));
        update_option('raise_button_color_border', get_option('button-color-border'));
        update_option('raise_button_color_border_hover', get_option('button-color-border-hover'));
        update_option('raise_button_color_border_active', get_option('button-color-border-active'));
        update_option('raise_button_color_text', get_option('button-color-text'));
        update_option('raise_button_color_text_hover', get_option('button-color-text-hover'));
        update_option('raise_button_color_text_active', get_option('button-color-text-active'));
        update_option('raise_widget_color_text_active', get_option('widget-color-text-active'));
        update_option('raise_confirm_button_color_background', get_option('confirm-button-color-background'));
        update_option('raise_confirm_button_color_background_hover', get_option('confirm-button-color-background-hover'));
        update_option('raise_confirm_button_color_border', get_option('confirm-button-color-border'));
        update_option('raise_confirm_button_color_border_hover', get_option('confirm-button-color-border-hover'));
        update_option('raise_confirm_button_color_text', get_option('confirm-button-color-text'));
        update_option('raise_confirm_button_color_text_hover', get_option('confirm-button-color-text-hover'));
        update_option('raise_tax_deduction_expose', get_option('tax-deduction-expose'));
        update_option('raise_tax_deduction_secret', get_option('tax-deduction-secret'));
        update_option('raise_tax_deduction_cache_ttl', get_option('tax-deduction-cache-ttl'));
        update_option('raise_tax_deduction_last_refreshed', get_option('tax-deduction-last-refreshed'));
        update_option('raise_tax_deduction_remote_url', get_option('tax-deduction-remote-url'));
        update_option('raise_tax_deduction_remote_form_name', get_option('tax-deduction-remote-form-name'));
        update_option('raise_tax_deduction_remote_settings', get_option('tax-deduction-remote-settings'));

        update_option('raise_settings', json_encode($settings));
        update_option('raise_version', '0.13.2');
    }

    // Add new updates above this line

    update_option('raise_version', $pluginVersion);
}
