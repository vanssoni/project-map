<?php
/**
 * Admin Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pmp_settings_nonce'])) {
    if (!wp_verify_nonce($_POST['pmp_settings_nonce'], 'pmp_save_settings')) {
        wp_die(__('Security check failed.', 'project-map-plugin'));
    }

    // Save settings
    update_option('pmp_mapbox_token', sanitize_text_field($_POST['mapbox_token']));
    update_option('pmp_map_style', sanitize_text_field($_POST['map_style']));
    update_option('pmp_osm_style', sanitize_text_field($_POST['osm_style']));
    update_option('pmp_enable_mapbox', isset($_POST['enable_mapbox']) ? '1' : '0');

    // Branding settings
    update_option('pmp_map_label', sanitize_text_field($_POST['map_label']));
    update_option('pmp_logo_image', esc_url_raw($_POST['logo_image']));

    // Color customization settings
    update_option('pmp_header_bg_color', sanitize_hex_color($_POST['header_bg_color']));
    update_option('pmp_header_text_color', sanitize_hex_color($_POST['header_text_color']));
    update_option('pmp_accent_color', sanitize_hex_color($_POST['accent_color']));
    update_option('pmp_button_text_color', sanitize_hex_color($_POST['button_text_color']));

    $message = __('Settings saved successfully.', 'project-map-plugin');
    $message_type = 'success';
}

// Get current settings
$mapbox_token = get_option('pmp_mapbox_token', '');
$map_style = get_option('pmp_map_style', 'dark-v11');
$enable_mapbox = get_option('pmp_enable_mapbox', '0');

// Branding settings
$map_label = get_option('pmp_map_label', 'Project Map');
$logo_image = get_option('pmp_logo_image', '');

// Color customization settings
$header_bg_color = get_option('pmp_header_bg_color', '#1d1d1d');
$header_text_color = get_option('pmp_header_text_color', '#ffffff');
$accent_color = get_option('pmp_accent_color', '#ffc220');
$button_text_color = get_option('pmp_button_text_color', '#2d2d2d');

// Map style options - Mapbox styles
$mapbox_styles = array(
    'dark-v11' => __('Dark', 'project-map-plugin'),
    'light-v11' => __('Light', 'project-map-plugin'),
    'streets-v12' => __('Streets', 'project-map-plugin'),
    'outdoors-v12' => __('Outdoors', 'project-map-plugin'),
    'satellite-v9' => __('Satellite', 'project-map-plugin'),
    'satellite-streets-v12' => __('Satellite Streets', 'project-map-plugin')
);

// OpenStreetMap styles
$osm_styles = array(
    'standard' => __('Standard', 'project-map-plugin'),
    'humanitarian' => __('Humanitarian', 'project-map-plugin'),
    'topo' => __('Topographic', 'project-map-plugin'),
    'dark' => __('Dark', 'project-map-plugin'),
    'light' => __('Light', 'project-map-plugin')
);

// Use appropriate styles based on Mapbox enable status
$map_styles = ($enable_mapbox == '1') ? $mapbox_styles : $osm_styles;

?>

<div class="wrap pmp-admin">
    <h1><?php _e('Project Map Settings', 'project-map-plugin'); ?></h1>
    <hr class="wp-header-end">

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" class="pmp-settings-form">
        <?php wp_nonce_field('pmp_save_settings', 'pmp_settings_nonce'); ?>

        <!-- Map Provider Settings -->
        <div class="pmp-settings-section">
            <h2><span class="dashicons dashicons-admin-site"></span> <?php _e('Map Provider', 'project-map-plugin'); ?></h2>

            <div class="pmp-form-field">
                <label>
                    <input type="checkbox" name="enable_mapbox" id="enable_mapbox" value="1" <?php checked($enable_mapbox, '1'); ?>>
                    <?php _e('Enable Mapbox', 'project-map-plugin'); ?>
                </label>
                <p class="description">
                    <?php _e('If disabled or no token provided, the plugin will use free OpenStreetMap with Leaflet.', 'project-map-plugin'); ?>
                </p>
            </div>

            <div class="pmp-form-field" id="mapbox_token_field" style="<?php echo ($enable_mapbox != '1') ? 'display: none;' : ''; ?>">
                <label for="mapbox_token"><?php _e('Mapbox Access Token', 'project-map-plugin'); ?></label>
                <input type="text" name="mapbox_token" id="mapbox_token" class="large-text"
                       value="<?php echo esc_attr($mapbox_token); ?>"
                       placeholder="pk.xxxxx...">
                <p class="description">
                    <?php _e('Get your free access token from', 'project-map-plugin'); ?>
                    <a href="https://account.mapbox.com/access-tokens/" target="_blank">Mapbox</a>.
                    <?php _e('Free tier includes 50,000 map loads per month.', 'project-map-plugin'); ?>
                </p>
            </div>

            <?php if ($enable_mapbox == '1' && empty($mapbox_token)): ?>
                <div class="pmp-warning-box" id="mapbox_warning" style="<?php echo ($enable_mapbox != '1') ? 'display: none;' : ''; ?>">
                    <span class="dashicons dashicons-warning"></span>
                    <strong><?php _e('Mapbox token is required when Mapbox is enabled!', 'project-map-plugin'); ?></strong>
                    <?php _e('The map will fall back to OpenStreetMap if no token is provided.', 'project-map-plugin'); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Branding Settings -->
        <div class="pmp-settings-section">
            <h2><span class="dashicons dashicons-format-image"></span> <?php _e('Branding', 'project-map-plugin'); ?></h2>

            <div class="pmp-form-row">
                <div class="pmp-form-field">
                    <label for="map_label"><?php _e('Map Header Label', 'project-map-plugin'); ?></label>
                    <input type="text" name="map_label" id="map_label" class="regular-text"
                           value="<?php echo esc_attr($map_label); ?>"
                           placeholder="Project Map">
                    <p class="description"><?php _e('Text displayed in the map header and loading screen.', 'project-map-plugin'); ?></p>
                </div>

                <div class="pmp-form-field">
                    <label for="logo_image"><?php _e('Logo Image', 'project-map-plugin'); ?></label>
                    <div class="pmp-logo-upload-wrap">
                        <input type="hidden" name="logo_image" id="logo_image" value="<?php echo esc_attr($logo_image); ?>">
                        <div class="pmp-logo-preview" id="pmp-logo-preview">
                            <?php if ($logo_image): ?>
                                <img src="<?php echo esc_url($logo_image); ?>" alt="Logo">
                            <?php else: ?>
                                <span class="pmp-logo-default">🌍</span>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button" id="pmp-upload-logo"><?php _e('Upload Logo', 'project-map-plugin'); ?></button>
                        <?php if ($logo_image): ?>
                            <button type="button" class="button pmp-remove-logo" id="pmp-remove-logo"><?php _e('Remove', 'project-map-plugin'); ?></button>
                        <?php endif; ?>
                    </div>
                    <p class="description"><?php _e('Upload a logo image for the header and loading screen. Default: 🌍 emoji.', 'project-map-plugin'); ?></p>
                </div>
            </div>
        </div>

        <!-- Map Display Settings -->
        <div class="pmp-settings-section">
            <h2><span class="dashicons dashicons-admin-appearance"></span> <?php _e('Map Display', 'project-map-plugin'); ?></h2>

            <div class="pmp-form-row">
                <!-- Mapbox Styles (shown when Mapbox enabled) -->
                <div class="pmp-form-field" id="mapbox_style_section" style="<?php echo ($enable_mapbox != '1') ? 'display: none;' : ''; ?>">
                    <label for="map_style"><?php _e('Map Style', 'project-map-plugin'); ?></label>
                    <select name="map_style" id="map_style">
                        <?php foreach ($mapbox_styles as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($map_style, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Choose from Mapbox map styles', 'project-map-plugin'); ?></p>
                </div>

                <!-- OSM Styles (shown when Mapbox disabled) -->
                <div class="pmp-form-field" id="osm_style_section" style="<?php echo ($enable_mapbox == '1') ? 'display: none;' : ''; ?>">
                    <label for="osm_style"><?php _e('Map Style', 'project-map-plugin'); ?></label>
                    <select name="osm_style" id="osm_style">
                        <?php
                        $osm_style = get_option('pmp_osm_style', 'standard');
                        foreach ($osm_styles as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($osm_style, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Choose from OpenStreetMap tile providers', 'project-map-plugin'); ?></p>
                </div>
            </div>
        </div>

        <!-- Color Customization -->
        <div class="pmp-settings-section">
            <h2><span class="dashicons dashicons-art"></span> <?php _e('Color Customization', 'project-map-plugin'); ?></h2>
            <p class="description"><?php _e('Customize colors for the map interface and report pages.', 'project-map-plugin'); ?></p>

            <div class="pmp-form-row">
                <div class="pmp-form-field">
                    <label for="header_bg_color"><?php _e('Header Background', 'project-map-plugin'); ?></label>
                    <input type="color" name="header_bg_color" id="header_bg_color"
                           value="<?php echo esc_attr($header_bg_color); ?>">
                    <p class="description"><?php _e('Background color for popup headers', 'project-map-plugin'); ?></p>
                </div>

                <div class="pmp-form-field">
                    <label for="header_text_color"><?php _e('Header Text', 'project-map-plugin'); ?></label>
                    <input type="color" name="header_text_color" id="header_text_color"
                           value="<?php echo esc_attr($header_text_color); ?>">
                    <p class="description"><?php _e('Text color for popup headers', 'project-map-plugin'); ?></p>
                </div>
            </div>

            <div class="pmp-form-row">
                <div class="pmp-form-field">
                    <label for="accent_color"><?php _e('Accent / Marker Color', 'project-map-plugin'); ?></label>
                    <input type="color" name="accent_color" id="accent_color"
                           value="<?php echo esc_attr($accent_color); ?>">
                    <p class="description"><?php _e('Used for buttons, markers, and highlights', 'project-map-plugin'); ?></p>
                </div>

                <div class="pmp-form-field">
                    <label for="button_text_color"><?php _e('Button Text', 'project-map-plugin'); ?></label>
                    <input type="color" name="button_text_color" id="button_text_color"
                           value="<?php echo esc_attr($button_text_color); ?>">
                    <p class="description"><?php _e('Text color for buttons', 'project-map-plugin'); ?></p>
                </div>
            </div>

            <!-- Live Preview -->
            <div class="pmp-color-preview-box">
                <h4><?php _e('Live Preview', 'project-map-plugin'); ?></h4>
                <div class="pmp-preview-container">
                    <!-- Marker Preview -->
                    <div class="pmp-preview-marker-section">
                        <div class="pmp-preview-marker" id="preview-marker"></div>
                        <span class="pmp-preview-marker-label"><?php _e('Map Marker', 'project-map-plugin'); ?></span>
                    </div>
                    <!-- Popup Preview -->
                    <div class="pmp-preview-popup" id="pmp-color-preview">
                        <div class="pmp-preview-header" id="preview-header">
                            <span><?php _e('Village Name, Country', 'project-map-plugin'); ?></span>
                        </div>
                        <div class="pmp-preview-body">
                            <div class="pmp-preview-image"><?php _e('Project Image', 'project-map-plugin'); ?></div>
                            <p><?php _e('Location details and project information will appear here...', 'project-map-plugin'); ?></p>
                            <button class="pmp-preview-button" id="preview-button"><?php _e('VIEW FULL REPORT', 'project-map-plugin'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="pmp-form-actions">
            <button type="submit" class="button button-primary button-large">
                <span class="dashicons dashicons-saved"></span>
                <?php _e('Save Settings', 'project-map-plugin'); ?>
            </button>
        </div>
    </form>

    <!-- Documentation -->
    <div class="pmp-settings-section pmp-documentation">
        <h2><span class="dashicons dashicons-book"></span> <?php _e('Documentation', 'project-map-plugin'); ?></h2>

        <div class="pmp-doc-grid">
            <div class="pmp-doc-item">
                <h3><?php _e('Shortcodes', 'project-map-plugin'); ?></h3>
                <table class="pmp-doc-table">
                    <tr>
                        <td><code>[project_map]</code></td>
                        <td><?php _e('Display the full project map', 'project-map-plugin'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[project_map country="India"]</code></td>
                        <td><?php _e('Filter by country name', 'project-map-plugin'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[project_map project_type="1"]</code></td>
                        <td><?php _e('Filter by project type ID (e.g., Water Project)', 'project-map-plugin'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[project_map solution_type="1"]</code></td>
                        <td><?php _e('Filter by solution type ID', 'project-map-plugin'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[project_map show_filters="false"]</code></td>
                        <td><?php _e('Hide filter controls', 'project-map-plugin'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[project_map show_stats="false"]</code></td>
                        <td><?php _e('Hide statistics', 'project-map-plugin'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[project_map show_search="false"]</code></td>
                        <td><?php _e('Hide search button', 'project-map-plugin'); ?></td>
                    </tr>
                    <tr>
                        <td><code>[project_map height="600px"]</code></td>
                        <td><?php _e('Custom map height', 'project-map-plugin'); ?></td>
                    </tr>
                </table>
            </div>

            <div class="pmp-doc-item">
                <h3><?php _e('Project Report URLs', 'project-map-plugin'); ?></h3>
                <p><?php _e('Individual project reports are accessible at:', 'project-map-plugin'); ?></p>
                <code><?php echo home_url('/project-report/{project_id}'); ?></code>
                <p class="description"><?php _e('Replace {project_id} with the actual project ID number.', 'project-map-plugin'); ?></p>
                <p class="description" style="margin-top: 10px;">
                    <strong><?php _e('Tip:', 'project-map-plugin'); ?></strong>
                    <?php _e('View Project Type and Solution Type IDs in their respective management pages.', 'project-map-plugin'); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<style>
/* Color Preview Styles */
.pmp-color-preview-box {
    background: #f6f7f7;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    margin-top: 20px;
}

.pmp-color-preview-box h4 {
    margin: 0 0 15px 0;
    font-size: 13px;
    color: #1d2327;
}

.pmp-preview-container {
    display: flex;
    align-items: flex-start;
    gap: 30px;
}

.pmp-preview-marker-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 20px;
    background: #e8e8e8;
    border-radius: 8px;
}

.pmp-preview-marker {
    width: 40px;
    height: 40px;
    background-color: <?php echo esc_attr($accent_color); ?>;
    border-radius: 50%;
    border: 4px solid #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    position: relative;
}

.pmp-preview-marker::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 0;
    border-left: 8px solid transparent;
    border-right: 8px solid transparent;
    border-top: 10px solid <?php echo esc_attr($accent_color); ?>;
}

.pmp-preview-marker-label {
    font-size: 12px;
    color: #555;
    font-weight: 600;
}

.pmp-preview-popup {
    width: 280px;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.pmp-preview-header {
    background-color: <?php echo esc_attr($header_bg_color); ?>;
    color: <?php echo esc_attr($header_text_color); ?>;
    padding: 18px 20px;
    font-weight: 600;
    font-size: 16px;
}

.pmp-preview-body {
    background: #fff;
    padding: 20px;
}

.pmp-preview-image {
    width: 100%;
    height: 100px;
    background: linear-gradient(135deg, #e0e0e0 0%, #f5f5f5 100%);
    border-radius: 6px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
    font-size: 12px;
}

.pmp-preview-body p {
    margin: 0 0 15px 0;
    color: #666;
    font-size: 14px;
    line-height: 1.5;
}

.pmp-preview-button {
    background-color: <?php echo esc_attr($accent_color); ?>;
    color: <?php echo esc_attr($button_text_color); ?>;
    border: none;
    padding: 12px 20px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    width: 100%;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Logo Upload */
.pmp-logo-upload-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 6px;
}

.pmp-logo-preview {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    border: 2px solid #ddd;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: #f9f9f9;
}

.pmp-logo-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.pmp-logo-default {
    font-size: 28px;
}

.pmp-remove-logo {
    color: #b32d2e !important;
}

@media (max-width: 600px) {
    .pmp-preview-container {
        flex-direction: column;
        align-items: center;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Live preview for color customization
    function updateColorPreview() {
        var headerBg = $('#header_bg_color').val();
        var headerText = $('#header_text_color').val();
        var accentColor = $('#accent_color').val();
        var buttonText = $('#button_text_color').val();

        $('#preview-header').css({
            'background-color': headerBg,
            'color': headerText
        });

        $('#preview-button').css({
            'background-color': accentColor,
            'color': buttonText
        });

        // Update marker preview
        $('#preview-marker').css('background-color', accentColor);
        // Update marker pointer (triangle) using a pseudo-element workaround
        var markerStyle = document.getElementById('pmp-marker-style');
        if (!markerStyle) {
            markerStyle = document.createElement('style');
            markerStyle.id = 'pmp-marker-style';
            document.head.appendChild(markerStyle);
        }
        markerStyle.textContent = '.pmp-preview-marker::after { border-top-color: ' + accentColor + ' !important; }';
    }

    $('#header_bg_color, #header_text_color, #accent_color, #button_text_color').on('input change', updateColorPreview);

    // Show/hide Mapbox token field based on toggle
    function toggleMapboxFields() {
        var isMapbox = $('#enable_mapbox').is(':checked');
        var $tokenField = $('#mapbox_token_field');
        var $warningBox = $('#mapbox_warning');
        var $mapboxStyleSection = $('#mapbox_style_section');
        var $osmStyleSection = $('#osm_style_section');

        // Show/hide Mapbox token field
        if (isMapbox) {
            $tokenField.slideDown();
            if ($warningBox.length) {
                $warningBox.slideDown();
            }
            $mapboxStyleSection.show();
            $osmStyleSection.hide();
        } else {
            $tokenField.slideUp();
            if ($warningBox.length) {
                $warningBox.slideUp();
            }
            $mapboxStyleSection.hide();
            $osmStyleSection.show();
        }
    }

    // Initialize on page load (without resetting values)
    toggleMapboxFields();

    // Update on toggle change
    $('#enable_mapbox').on('change', toggleMapboxFields);

    // Logo upload via WordPress Media Library
    var logoFrame;
    $('#pmp-upload-logo').on('click', function(e) {
        e.preventDefault();
        if (logoFrame) {
            logoFrame.open();
            return;
        }
        logoFrame = wp.media({
            title: '<?php echo esc_js(__('Select Logo Image', 'project-map-plugin')); ?>',
            button: { text: '<?php echo esc_js(__('Use as Logo', 'project-map-plugin')); ?>' },
            multiple: false
        });
        logoFrame.on('select', function() {
            var attachment = logoFrame.state().get('selection').first().toJSON();
            $('#logo_image').val(attachment.url);
            $('#pmp-logo-preview').html('<img src="' + attachment.url + '" alt="Logo">');
            if (!$('#pmp-remove-logo').length) {
                $('<button type="button" class="button pmp-remove-logo" id="pmp-remove-logo"><?php echo esc_js(__('Remove', 'project-map-plugin')); ?></button>').insertAfter('#pmp-upload-logo');
                bindRemoveLogo();
            }
        });
        logoFrame.open();
    });

    function bindRemoveLogo() {
        $(document).on('click', '#pmp-remove-logo', function(e) {
            e.preventDefault();
            $('#logo_image').val('');
            $('#pmp-logo-preview').html('<span class="pmp-logo-default">🌍</span>');
            $(this).remove();
        });
    }
    bindRemoveLogo();
});
</script>
