<?php if (!defined('ABSPATH')) exit;

// Add updates to the settings at the bottom

/**
 * Update settings
 */
function updateSettings()
{
    //return;

    $pluginVersion = getPluginVersion();

    // Get settings version
    $settingsVersion = get_option('version');
    if (empty($settingsVersion)) {
        // Fresh install, version not set yet, do it now
        $settingsVersion = $pluginVersion;
        update_option('version', $pluginVersion);
    }

    if ($pluginVersion == $settingsVersion) {
        // Everything up-to-date
        return;
    }

    // Get current settings
    $settings = json_decode(get_option('settings'), true);

    if (empty($settings)) {
        // No settings to be updated
        return;
    }

    /**
     * Date:   2017-01-06
     * Author: Naoki Peter
     */
    if (version_compare('0.1.24', $settingsVersion, '>')) {
        // Rename web_hook node to webhook
        foreach (array_keys($settings['forms']) as $formName) {
            if (isset($settings['forms'][$formName]['web_hook'])) {
                $settings['forms'][$formName]['webhook'] = $settings['forms'][$formName]['web_hook'];
                unset($settings['forms'][$formName]['web_hook']);
            }
        }

        // Save changes
        update_option('settings', json_encode($settings));
        update_option('version', '0.1.24');
    }

    /**
     * Date:   2017-01-11
     * Author: Naoki Peter
     */
    if (version_compare('0.1.27', $settingsVersion, '>')) {
        // HookPress plugin is obsolete now. Move HookPress endpoints to plugin settings.
        if (function_exists('hookpress_get_hooks')) {
            $hooks = hookpress_get_hooks();

            // Replace webhook names with actual webhook endpoints from (e.g. Zapier URLs)
            foreach (array_keys($settings['forms']) as $formName) {
                // Logging
                if (isset($settings['forms'][$formName]['webhook']['logging'])) {
                    $hookNames    = $settings['forms'][$formName]['webhook']['logging'];
                    $loggingHooks = array_filter($hooks, function ($hook) use ($hookNames) {
                        return strpos($hook['hook'], 'eas_donation_logging_') === 0
                               && in_array(end(explode('_', $hook['hook'])), $hookNames);
                    });
                    $settings['forms'][$formName]['webhook']['logging'] = array_map(function ($hook) {
                        return $hook['url'];
                    }, $loggingHooks);
                }

                // Mailing lists
                if (isset($settings['forms'][$formName]['webhook']['mailing_list'])) {
                    $hookNames        = $settings['forms'][$formName]['webhook']['mailing_list'];
                    $mailingListHooks = array_filter($hooks, function ($hook) use ($hookNames) {
                        return strpos($hook['hook'], 'eas_donation_mailinglist_') === 0
                               && in_array(end(explode('_', $hook['hook'])), $hookNames);
                    });
                    $settings['forms'][$formName]['webhook']['mailing_list'] = array_values(array_map(function ($hook) {
                        return $hook['url'];
                    }, $mailingListHooks));
                }
            }

            // Save changes
            update_option('settings', json_encode($settings));
        }
        update_option('version', '0.1.27');
    }

    // Add new updates above this line

    update_option('version', $pluginVersion);
}
