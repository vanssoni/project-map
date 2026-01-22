<?php
/**
 * Project Detail Shortcode Template
 * Use: [project_detail id="123"]
 */

if (!defined('ABSPATH')) {
    exit;
}

$project_id = intval($atts['id']);

if (!$project_id) {
    echo '<p>' . __('Please specify a project ID.', 'project-map-plugin') . '</p>';
    return;
}

$project = ProjectMapPlugin::get_project($project_id);

if (!$project || $project->status !== 'publish') {
    echo '<p>' . __('Project not found.', 'project-map-plugin') . '</p>';
    return;
}

// Get featured image
$featured_image = $project->featured_image_id ? wp_get_attachment_url($project->featured_image_id) : '';

// Format completion date
$months = array(
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
);
$completion_date = '';
if ($project->completion_year) {
    $completion_date = $project->completion_month ? $months[$project->completion_month] . ' ' . $project->completion_year : $project->completion_year;
}
?>

<div class="pmp-project-card">
    <?php if ($featured_image): ?>
    <div class="pmp-project-card-image">
        <img src="<?php echo esc_url($featured_image); ?>" alt="<?php echo esc_attr($project->village_name); ?>">
    </div>
    <?php endif; ?>

    <div class="pmp-project-card-content">
        <h3 class="pmp-project-card-title"><?php echo esc_html($project->village_name); ?></h3>

        <div class="pmp-project-card-location">
            <?php if ($project->country_flag): ?>
                <span class="pmp-flag"><?php echo $project->country_flag; ?></span>
            <?php endif; ?>
            <span><?php echo esc_html($project->country); ?></span>
        </div>

        <div class="pmp-project-card-stats">
            <div class="pmp-card-stat">
                <span class="pmp-card-stat-value"><?php echo number_format($project->beneficiaries); ?></span>
                <span class="pmp-card-stat-label"><?php _e('People Served', 'project-map-plugin'); ?></span>
            </div>

            <?php if ($project->solution_type_name): ?>
            <div class="pmp-card-stat">
                <span class="pmp-card-stat-value"><?php echo esc_html($project->solution_type_name); ?></span>
                <span class="pmp-card-stat-label"><?php _e('Solution Type', 'project-map-plugin'); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($completion_date): ?>
            <div class="pmp-card-stat">
                <span class="pmp-card-stat-value"><?php echo esc_html($completion_date); ?></span>
                <span class="pmp-card-stat-label"><?php _e('Completed', 'project-map-plugin'); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($project->description): ?>
        <div class="pmp-project-card-description">
            <?php echo wp_trim_words($project->description, 50); ?>
        </div>
        <?php endif; ?>

        <a href="<?php echo home_url('/project-report/' . $project->id); ?>" class="pmp-project-card-button">
            <?php _e('View Full Report', 'project-map-plugin'); ?> →
        </a>
    </div>
</div>
