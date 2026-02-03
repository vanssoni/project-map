<?php
/**
 * Project Map Shortcode Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get project types for filter
$project_types = ProjectMapPlugin::get_project_types();

// Get solution types for filter
$solution_types = ProjectMapPlugin::get_solution_types();

// Get countries with projects for filter
$countries = ProjectMapPlugin::get_countries_with_projects();

// Shortcode attributes
$show_filters = filter_var($atts['show_filters'], FILTER_VALIDATE_BOOLEAN);
$show_stats = filter_var($atts['show_stats'], FILTER_VALIDATE_BOOLEAN);
$show_search = filter_var($atts['show_search'], FILTER_VALIDATE_BOOLEAN);
$map_height = esc_attr($atts['height']);
$initial_country = esc_attr($atts['country']);
$initial_project_type = intval($atts['project_type']);
$initial_solution_type = intval($atts['solution_type']);

// Branding
$map_label = get_option('pmp_map_label', 'Project Map');
$logo_image = get_option('pmp_logo_image', '');

// Color customization
$header_bg_color = get_option('pmp_header_bg_color', '#1d1d1d');
$header_text_color = get_option('pmp_header_text_color', '#ffffff');
$accent_color = get_option('pmp_accent_color', '#ffc220');
$button_text_color = get_option('pmp_button_text_color', '#2d2d2d');
?>

<div class="pmp-map-wrapper"
     data-country="<?php echo $initial_country; ?>"
     data-project-type="<?php echo $initial_project_type; ?>"
     data-solution-type="<?php echo $initial_solution_type; ?>"
     style="--pmp-header-bg-color: <?php echo esc_attr($header_bg_color); ?>; --pmp-header-text-color: <?php echo esc_attr($header_text_color); ?>; --pmp-accent-color: <?php echo esc_attr($accent_color); ?>; --pmp-button-text-color: <?php echo esc_attr($button_text_color); ?>;">

    <!-- Loading Screen -->
    <div class="pmp-loading-screen" id="pmp-loading">
        <div class="pmp-loading-content">
            <?php if ($logo_image): ?>
                <div class="pmp-loader-icon"><img src="<?php echo esc_url($logo_image); ?>" alt="" class="pmp-loader-logo-img"></div>
            <?php else: ?>
                <div class="pmp-loader-icon">🌍</div>
            <?php endif; ?>
            <p><?php echo esc_html(sprintf(__('Loading %s...', 'project-map-plugin'), $map_label)); ?></p>
        </div>
    </div>

    <!-- Header Section -->
    <div class="pmp-header">
        <div class="pmp-header-content">
            <!-- Brand -->
            <div class="pmp-brand">
                <?php if ($logo_image): ?>
                    <div class="pmp-logo"><img src="<?php echo esc_url($logo_image); ?>" alt="" class="pmp-header-logo-img"></div>
                <?php else: ?>
                    <div class="pmp-logo">🌍</div>
                <?php endif; ?>
                <div class="pmp-brand-text">
                    <h2 class="pmp-title"><?php echo esc_html($map_label); ?></h2>
                    <p class="pmp-last-updated"><?php _e('Last updated:', 'project-map-plugin'); ?> <?php echo date_i18n(get_option('date_format')); ?></p>
                </div>
            </div>

            <?php if ($show_stats): ?>
            <!-- Statistics -->
            <div class="pmp-statistics">
                <div class="pmp-stat-item">
                    <div class="pmp-stat-number" id="pmp-stat-people">0</div>
                    <div class="pmp-stat-label"><?php _e('people served', 'project-map-plugin'); ?></div>
                </div>
                <div class="pmp-stat-divider"></div>
                <div class="pmp-stat-item">
                    <div class="pmp-stat-number" id="pmp-stat-projects">0</div>
                    <div class="pmp-stat-label"><?php _e('projects', 'project-map-plugin'); ?></div>
                </div>
                <div class="pmp-stat-divider"></div>
                <div class="pmp-stat-item">
                    <div class="pmp-stat-number" id="pmp-stat-countries">0</div>
                    <div class="pmp-stat-label"><?php _e('countries', 'project-map-plugin'); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($show_filters): ?>
            <!-- Controls -->
            <div class="pmp-controls">
                <?php if (empty($initial_country)): ?>
                <div class="pmp-dropdown-wrapper">
                    <select id="pmp-country-filter" class="pmp-country-dropdown">
                        <option value=""><?php _e('Explore by Country', 'project-map-plugin'); ?></option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo esc_attr($country->name); ?>">
                                <?php echo esc_html($country->flag . ' ' . $country->name); ?> (<?php echo $country->project_count; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if (!empty($project_types) && empty($initial_project_type)): ?>
                <div class="pmp-dropdown-wrapper">
                    <select id="pmp-project-type-filter" class="pmp-project-type-dropdown">
                        <option value=""><?php _e('All Project Types', 'project-map-plugin'); ?></option>
                        <?php foreach ($project_types as $type): ?>
                            <option value="<?php echo esc_attr($type->id); ?>">
                                <?php echo esc_html($type->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if (empty($initial_solution_type)): ?>
                <div class="pmp-dropdown-wrapper">
                    <select id="pmp-type-filter" class="pmp-type-dropdown">
                        <option value=""><?php _e('All Solution Types', 'project-map-plugin'); ?></option>
                        <?php foreach ($solution_types as $type): ?>
                            <option value="<?php echo esc_attr($type->id); ?>">
                                <?php echo esc_html($type->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ($show_search): ?>
                <button class="pmp-search-button" id="pmp-search-toggle">
                    <span class="pmp-search-icon">🔍</span>
                    <?php _e('Search', 'project-map-plugin'); ?>
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($show_search): ?>
        <!-- Search Bar -->
        <div class="pmp-search-bar" id="pmp-search-bar">
            <input type="text" id="pmp-search-input" placeholder="<?php _e('Search projects by village name, project number or location...', 'project-map-plugin'); ?>" autocomplete="off">
            <button class="pmp-search-close" id="pmp-search-close">✕</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Map Container -->
    <div id="pmp-map" class="pmp-map-container" style="height: <?php echo $map_height; ?>;">
        <!-- Project Popup Panel -->
        <div id="pmp-project-popup" class="pmp-project-popup">
            <div class="pmp-popup-content">
                <button class="pmp-popup-close" id="pmp-popup-close">✕</button>

                <div class="pmp-popup-header">
                    <h2 id="pmp-popup-title"></h2>
                    <div class="pmp-popup-location">
                        <span id="pmp-popup-flag"></span>
                        <span id="pmp-popup-country"></span>
                        <span class="pmp-coordinates" id="pmp-popup-coordinates"></span>
                    </div>
                </div>

                <div class="pmp-popup-body">
                    <div class="pmp-popup-image-container">
                        <img id="pmp-popup-image" src="" alt="">
                    </div>

                    <div class="pmp-popup-stats">
                        <div class="pmp-main-stat">
                            <div class="pmp-stat-label-small"><?php _e('PEOPLE SERVED', 'project-map-plugin'); ?></div>
                            <div class="pmp-stat-number-large" id="pmp-popup-people-served">0</div>
                        </div>

                        <div class="pmp-popup-details">
                            <div class="pmp-detail-row">
                                <div class="pmp-detail-item">
                                    <div class="pmp-detail-label"><?php _e('PROJECT TYPE', 'project-map-plugin'); ?></div>
                                    <div class="pmp-detail-value" id="pmp-popup-project-type"></div>
                                </div>
                                <div class="pmp-detail-item">
                                    <div class="pmp-detail-label"><?php _e('SOLUTION TYPE', 'project-map-plugin'); ?></div>
                                    <div class="pmp-detail-value" id="pmp-popup-solution-type"></div>
                                </div>
                            </div>
                            <div class="pmp-detail-row">
                                <div class="pmp-detail-item">
                                    <div class="pmp-detail-label"><?php _e('COMPLETED', 'project-map-plugin'); ?></div>
                                    <div class="pmp-detail-value" id="pmp-popup-date"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <a href="#" class="pmp-view-report-button" id="pmp-view-report-button" style="background-color: <?php echo esc_attr($accent_color); ?>; color: <?php echo esc_attr($button_text_color); ?>;"><?php _e('VIEW FULL REPORT', 'project-map-plugin'); ?></a>
                </div>
            </div>
        </div>

        <!-- Popup Overlay -->
        <div id="pmp-popup-overlay" class="pmp-popup-overlay"></div>
    </div>
</div>
