<?php
/**
 * Admin Add/Edit Project Page
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$projects_table = $wpdb->prefix . 'pmp_projects';

$editing = isset($_GET['id']);
$project = null;
$message = '';
$message_type = '';

if ($editing) {
    $id = intval($_GET['id']);
    $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $projects_table WHERE id = %d", $id));
    if (!$project) {
        wp_die(__('Project not found.', 'project-map-plugin'));
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pmp_project_nonce'])) {
    if (!wp_verify_nonce($_POST['pmp_project_nonce'], 'pmp_save_project')) {
        wp_die(__('Security check failed.', 'project-map-plugin'));
    }

    // Prepare gallery images
    $gallery_images = '';
    if (!empty($_POST['gallery_images'])) {
        $gallery_images = sanitize_text_field($_POST['gallery_images']);
    }

    // Prepare video URLs
    $video_urls = '';
    if (!empty($_POST['video_urls'])) {
        $videos = array_filter(array_map('esc_url_raw', explode("\n", $_POST['video_urls'])));
        $video_urls = implode("\n", $videos);
    }

    $data = array(
        'village_name' => sanitize_text_field($_POST['village_name']),
        'project_number' => sanitize_text_field($_POST['project_number']),
        'country' => sanitize_text_field($_POST['country']),
        'gps_latitude' => floatval($_POST['gps_latitude']),
        'gps_longitude' => floatval($_POST['gps_longitude']),
        'completion_month' => !empty($_POST['completion_month']) ? intval($_POST['completion_month']) : null,
        'completion_year' => !empty($_POST['completion_year']) ? intval($_POST['completion_year']) : null,
        'beneficiaries' => intval($_POST['beneficiaries']),
        'in_honour_of' => sanitize_text_field($_POST['in_honour_of']),
        'project_type_id' => !empty($_POST['project_type_id']) ? intval($_POST['project_type_id']) : null,
        'solution_type_id' => !empty($_POST['solution_type_id']) ? intval($_POST['solution_type_id']) : null,
        'featured_image_id' => !empty($_POST['featured_image_id']) ? intval($_POST['featured_image_id']) : null,
        'gallery_images' => $gallery_images,
        'video_urls' => $video_urls,
        'description' => wp_kses_post($_POST['description']),
        'status' => sanitize_text_field($_POST['status'])
    );

    $format = array('%s', '%s', '%s', '%f', '%f', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s');

    if ($editing) {
        $result = $wpdb->update($projects_table, $data, array('id' => $id), $format, array('%d'));
        if ($result !== false) {
            $message = __('Project updated successfully.', 'project-map-plugin');
            $message_type = 'success';
            // Refresh project data
            $project = $wpdb->get_row($wpdb->prepare("SELECT * FROM $projects_table WHERE id = %d", $id));
        } else {
            $message = __('Error updating project.', 'project-map-plugin');
            $message_type = 'error';
        }
    } else {
        $result = $wpdb->insert($projects_table, $data, $format);
        if ($result) {
            $new_id = $wpdb->insert_id;
            wp_redirect(admin_url('admin.php?page=pmp-add-project&id=' . $new_id . '&created=1'));
            exit;
        } else {
            $message = __('Error creating project.', 'project-map-plugin');
            $message_type = 'error';
        }
    }
}

// Check for created message
if (isset($_GET['created']) && $_GET['created'] == 1) {
    $message = __('Project created successfully.', 'project-map-plugin');
    $message_type = 'success';
}

// Get project types, solution types, and countries
$project_types = ProjectMapPlugin::get_project_types();
$solution_types = ProjectMapPlugin::get_solution_types();
$countries = ProjectMapPlugin::get_countries();

// Generate months array
$months = array(
    1 => __('January', 'project-map-plugin'),
    2 => __('February', 'project-map-plugin'),
    3 => __('March', 'project-map-plugin'),
    4 => __('April', 'project-map-plugin'),
    5 => __('May', 'project-map-plugin'),
    6 => __('June', 'project-map-plugin'),
    7 => __('July', 'project-map-plugin'),
    8 => __('August', 'project-map-plugin'),
    9 => __('September', 'project-map-plugin'),
    10 => __('October', 'project-map-plugin'),
    11 => __('November', 'project-map-plugin'),
    12 => __('December', 'project-map-plugin')
);

// Generate years array (from 2000 to current year + 5)
$current_year = date('Y');
$years = range($current_year + 5, 2000);

// Get featured image URL
$featured_image_url = '';
if ($project && $project->featured_image_id) {
    $featured_image_url = wp_get_attachment_image_url($project->featured_image_id, 'medium');
}
?>

<div class="wrap pmp-admin">
    <h1 class="wp-heading-inline">
        <?php echo $editing ? __('Edit Project', 'project-map-plugin') : __('Add New Project', 'project-map-plugin'); ?>
    </h1>
    <a href="<?php echo admin_url('admin.php?page=pmp-projects'); ?>" class="page-title-action"><?php _e('Back to Projects', 'project-map-plugin'); ?></a>
    <hr class="wp-header-end">

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" class="pmp-project-form">
        <?php wp_nonce_field('pmp_save_project', 'pmp_project_nonce'); ?>

        <div class="pmp-form-layout">
            <!-- Main Content Column -->
            <div class="pmp-form-main">
                <!-- Basic Information -->
                <div class="pmp-form-section">
                    <h2><?php _e('Basic Information', 'project-map-plugin'); ?></h2>

                    <div class="pmp-form-row">
                        <div class="pmp-form-field pmp-field-large">
                            <label for="village_name"><?php _e('Village/Project Name', 'project-map-plugin'); ?> <span class="required">*</span></label>
                            <input type="text" name="village_name" id="village_name" class="regular-text" required
                                   value="<?php echo $project ? esc_attr($project->village_name) : ''; ?>">
                        </div>
                        <div class="pmp-form-field">
                            <label for="project_number"><?php _e('Project Number', 'project-map-plugin'); ?></label>
                            <input type="text" name="project_number" id="project_number"
                                   value="<?php echo $project ? esc_attr($project->project_number) : ''; ?>"
                                   placeholder="e.g., PRJ-001">
                        </div>
                    </div>

                    <div class="pmp-form-row">
                        <div class="pmp-form-field">
                            <label for="country"><?php _e('Country', 'project-map-plugin'); ?> <span class="required">*</span></label>
                            <select name="country" id="country" required>
                                <option value=""><?php _e('Select Country', 'project-map-plugin'); ?></option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo esc_attr($country->name); ?>"
                                        <?php selected($project ? $project->country : '', $country->name); ?>>
                                        <?php echo esc_html($country->flag . ' ' . $country->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="pmp-form-field">
                            <label for="project_type_id"><?php _e('Project Type', 'project-map-plugin'); ?></label>
                            <select name="project_type_id" id="project_type_id">
                                <option value=""><?php _e('Select Project Type', 'project-map-plugin'); ?></option>
                                <?php foreach ($project_types as $type): ?>
                                    <option value="<?php echo esc_attr($type->id); ?>"
                                        <?php selected($project ? $project->project_type_id : '', $type->id); ?>>
                                        <?php echo esc_html(($type->icon ? $type->icon . ' ' : '') . $type->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="pmp-form-row">
                        <div class="pmp-form-field">
                            <label for="solution_type_id"><?php _e('Solution Type', 'project-map-plugin'); ?></label>
                            <select name="solution_type_id" id="solution_type_id">
                                <option value=""><?php _e('Select Solution Type', 'project-map-plugin'); ?></option>
                                <?php foreach ($solution_types as $type): ?>
                                    <option value="<?php echo esc_attr($type->id); ?>"
                                        <?php selected($project ? $project->solution_type_id : '', $type->id); ?>>
                                        <?php echo esc_html($type->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="pmp-form-field">
                            <!-- Empty for layout balance -->
                        </div>
                    </div>
                </div>

                <!-- GPS Coordinates -->
                <div class="pmp-form-section">
                    <h2><?php _e('GPS Coordinates', 'project-map-plugin'); ?></h2>

                    <div class="pmp-form-row">
                        <div class="pmp-form-field">
                            <label for="gps_latitude"><?php _e('Latitude', 'project-map-plugin'); ?> <span class="required">*</span></label>
                            <input type="number" step="any" name="gps_latitude" id="gps_latitude" required
                                   value="<?php echo $project ? esc_attr($project->gps_latitude) : ''; ?>"
                                   placeholder="e.g., 20.1685">
                        </div>
                        <div class="pmp-form-field">
                            <label for="gps_longitude"><?php _e('Longitude', 'project-map-plugin'); ?> <span class="required">*</span></label>
                            <input type="number" step="any" name="gps_longitude" id="gps_longitude" required
                                   value="<?php echo $project ? esc_attr($project->gps_longitude) : ''; ?>"
                                   placeholder="e.g., 84.88479">
                        </div>
                    </div>

                    <div class="pmp-geocoding-help">
                        <p><span class="dashicons dashicons-info"></span> <?php _e('To find GPS coordinates:', 'project-map-plugin'); ?></p>
                        <ol>
                            <li><?php _e('Go to Google Maps', 'project-map-plugin'); ?></li>
                            <li><?php _e('Right-click on the location', 'project-map-plugin'); ?></li>
                            <li><?php _e('Click the coordinates to copy them', 'project-map-plugin'); ?></li>
                        </ol>
                    </div>
                </div>

                <!-- Project Details -->
                <div class="pmp-form-section">
                    <h2><?php _e('Project Details', 'project-map-plugin'); ?></h2>

                    <div class="pmp-form-row">
                        <div class="pmp-form-field">
                            <label for="completion_month"><?php _e('Completion Month', 'project-map-plugin'); ?></label>
                            <select name="completion_month" id="completion_month">
                                <option value=""><?php _e('Select Month', 'project-map-plugin'); ?></option>
                                <?php foreach ($months as $num => $name): ?>
                                    <option value="<?php echo $num; ?>"
                                        <?php selected($project ? $project->completion_month : '', $num); ?>>
                                        <?php echo esc_html($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="pmp-form-field">
                            <label for="completion_year"><?php _e('Completion Year', 'project-map-plugin'); ?></label>
                            <select name="completion_year" id="completion_year">
                                <option value=""><?php _e('Select Year', 'project-map-plugin'); ?></option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year; ?>"
                                        <?php selected($project ? $project->completion_year : '', $year); ?>>
                                        <?php echo $year; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="pmp-form-row">
                        <div class="pmp-form-field">
                            <label for="beneficiaries"><?php _e('Number of Beneficiaries', 'project-map-plugin'); ?></label>
                            <input type="number" name="beneficiaries" id="beneficiaries" min="0"
                                   value="<?php echo $project ? esc_attr($project->beneficiaries) : ''; ?>"
                                   placeholder="e.g., 500">
                        </div>
                        <div class="pmp-form-field">
                            <label for="in_honour_of"><?php _e('In Honour Of / Funded By', 'project-map-plugin'); ?></label>
                            <input type="text" name="in_honour_of" id="in_honour_of"
                                   value="<?php echo $project ? esc_attr($project->in_honour_of) : ''; ?>"
                                   placeholder="e.g., The Johnson Family">
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="pmp-form-section">
                    <h2><?php _e('Project Description', 'project-map-plugin'); ?></h2>

                    <div class="pmp-form-field pmp-field-full">
                        <?php
                        wp_editor(
                            $project ? $project->description : '',
                            'description',
                            array(
                                'textarea_name' => 'description',
                                'textarea_rows' => 10,
                                'media_buttons' => true,
                                'teeny' => false,
                                'quicktags' => true
                            )
                        );
                        ?>
                    </div>
                </div>

                <!-- Video URLs -->
                <div class="pmp-form-section">
                    <h2><?php _e('Video URLs', 'project-map-plugin'); ?></h2>

                    <div class="pmp-form-field pmp-field-full">
                        <label for="video_urls"><?php _e('Video URLs (one per line)', 'project-map-plugin'); ?></label>
                        <textarea name="video_urls" id="video_urls" rows="4" placeholder="https://www.youtube.com/watch?v=..."><?php echo $project ? esc_textarea($project->video_urls) : ''; ?></textarea>
                        <p class="description"><?php _e('Enter YouTube, Vimeo, or direct video URLs, one per line.', 'project-map-plugin'); ?></p>

                        <div class="pmp-video-help">
                            <p><strong><?php _e('How to add videos:', 'project-map-plugin'); ?></strong></p>
                            <ul>
                                <li><strong><?php _e('YouTube/Vimeo:', 'project-map-plugin'); ?></strong> <?php _e('Simply paste the video URL (e.g., https://www.youtube.com/watch?v=XXXXX)', 'project-map-plugin'); ?></li>
                                <li><strong><?php _e('Upload your own video:', 'project-map-plugin'); ?></strong>
                                    <ol>
                                        <li><?php _e('Go to <strong>Media &rarr; Add New</strong> in WordPress admin', 'project-map-plugin'); ?></li>
                                        <li><?php _e('Upload your video file (MP4 recommended)', 'project-map-plugin'); ?></li>
                                        <li><?php _e('Click on the uploaded video to open it', 'project-map-plugin'); ?></li>
                                        <li><?php _e('Copy the <strong>File URL</strong> from the right panel', 'project-map-plugin'); ?></li>
                                        <li><?php _e('Paste the URL here', 'project-map-plugin'); ?></li>
                                    </ol>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Column -->
            <div class="pmp-form-sidebar">
                <!-- Publish Box -->
                <div class="pmp-form-section pmp-publish-box">
                    <h2><?php _e('Publish', 'project-map-plugin'); ?></h2>

                    <div class="pmp-form-field">
                        <label for="status"><?php _e('Status', 'project-map-plugin'); ?></label>
                        <select name="status" id="status">
                            <option value="publish" <?php selected($project ? $project->status : 'publish', 'publish'); ?>><?php _e('Published', 'project-map-plugin'); ?></option>
                            <option value="draft" <?php selected($project ? $project->status : '', 'draft'); ?>><?php _e('Draft', 'project-map-plugin'); ?></option>
                        </select>
                    </div>

                    <div class="pmp-publish-actions">
                        <?php if ($editing): ?>
                            <a href="<?php echo home_url('/project-report/' . $project->id); ?>" target="_blank" class="button"><?php _e('View Project', 'project-map-plugin'); ?></a>
                        <?php endif; ?>
                        <button type="submit" class="button button-primary button-large">
                            <?php echo $editing ? __('Update Project', 'project-map-plugin') : __('Add Project', 'project-map-plugin'); ?>
                        </button>
                    </div>
                </div>

                <!-- Featured Image -->
                <div class="pmp-form-section">
                    <h2><?php _e('Featured Image', 'project-map-plugin'); ?></h2>

                    <div class="pmp-image-upload" id="featured-image-upload">
                        <input type="hidden" name="featured_image_id" id="featured_image_id"
                               value="<?php echo $project ? esc_attr($project->featured_image_id) : ''; ?>">

                        <div class="pmp-image-preview" id="featured-image-preview" <?php echo $featured_image_url ? '' : 'style="display:none;"'; ?>>
                            <img src="<?php echo esc_url($featured_image_url); ?>" alt="">
                            <button type="button" class="pmp-remove-image" id="remove-featured-image">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>

                        <button type="button" class="button pmp-upload-button" id="upload-featured-image" <?php echo $featured_image_url ? 'style="display:none;"' : ''; ?>>
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e('Upload Image', 'project-map-plugin'); ?>
                        </button>
                    </div>
                </div>

                <!-- Image Gallery -->
                <div class="pmp-form-section">
                    <h2><?php _e('Image Gallery', 'project-map-plugin'); ?></h2>

                    <div class="pmp-gallery-upload" id="gallery-upload">
                        <input type="hidden" name="gallery_images" id="gallery_images"
                               value="<?php echo $project ? esc_attr($project->gallery_images) : ''; ?>">

                        <div class="pmp-gallery-preview" id="gallery-preview">
                            <?php
                            if ($project && $project->gallery_images) {
                                $gallery_ids = explode(',', $project->gallery_images);
                                foreach ($gallery_ids as $img_id) {
                                    $img_url = wp_get_attachment_image_url(intval($img_id), 'thumbnail');
                                    if ($img_url) {
                                        echo '<div class="pmp-gallery-item" data-id="' . esc_attr($img_id) . '">';
                                        echo '<img src="' . esc_url($img_url) . '" alt="">';
                                        echo '<button type="button" class="pmp-remove-gallery-image"><span class="dashicons dashicons-no-alt"></span></button>';
                                        echo '</div>';
                                    }
                                }
                            }
                            ?>
                        </div>

                        <button type="button" class="button pmp-upload-button" id="upload-gallery-images">
                            <span class="dashicons dashicons-images-alt2"></span>
                            <?php _e('Add Images', 'project-map-plugin'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
