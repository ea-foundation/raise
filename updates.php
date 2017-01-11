<?php if (!defined('ABSPATH')) exit;

/**
 * Updates settings
 */
function updateSettings()
{
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
        // No settings to update
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

    // Add new updates above this line

    update_option('version', $pluginVersion);
}
