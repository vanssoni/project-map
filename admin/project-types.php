<?php
/**
 * Project Types Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'pmp_project_types';
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pmp_project_type_nonce'])) {
    if (!wp_verify_nonce($_POST['pmp_project_type_nonce'], 'pmp_project_type_action')) {
        $error = __('Security check failed.', 'project-map-plugin');
    } else {
        $action = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';

        if ($action === 'add' || $action === 'edit') {
            $type_id = isset($_POST['type_id']) ? intval($_POST['type_id']) : 0;
            $name = sanitize_text_field($_POST['type_name']);
            $description = sanitize_textarea_field($_POST['type_description']);

            if (empty($name)) {
                $error = __('Project type name is required.', 'project-map-plugin');
            } else {
                $data = array(
                    'name' => $name,
                    'description' => $description
                );

                if ($action === 'edit' && $type_id > 0) {
                    $wpdb->update($table_name, $data, array('id' => $type_id));
                    $message = __('Project type updated successfully.', 'project-map-plugin');
                } else {
                    $wpdb->insert($table_name, $data);
                    $message = __('Project type added successfully.', 'project-map-plugin');
                }
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['_wpnonce'])) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'delete_project_type_' . $_GET['delete'])) {
        $delete_id = intval($_GET['delete']);

        // Check if there are projects using this type
        $projects_table = $wpdb->prefix . 'pmp_projects';
        $project_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $projects_table WHERE project_type_id = %d",
            $delete_id
        ));

        if ($project_count > 0) {
            $error = sprintf(__('Cannot delete: %d project(s) are using this project type.', 'project-map-plugin'), $project_count);
        } else {
            $wpdb->delete($table_name, array('id' => $delete_id));
            $message = __('Project type deleted successfully.', 'project-map-plugin');
        }
    }
}

// Get all project types with counts
$project_types = $wpdb->get_results("
    SELECT pt.*,
           COUNT(DISTINCT p.id) as project_count,
           COUNT(DISTINCT st.id) as solution_count
    FROM $table_name pt
    LEFT JOIN {$wpdb->prefix}pmp_projects p ON p.project_type_id = pt.id
    LEFT JOIN {$wpdb->prefix}pmp_solution_types st ON st.project_type_id = pt.id
    GROUP BY pt.id
    ORDER BY pt.name ASC
");

// Get type for editing
$edit_type = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_type = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_id));
}
?>

<div class="wrap pmp-admin-wrap">
    <div class="pmp-admin-header">
        <h1><?php _e('Project Types', 'project-map-plugin'); ?></h1>
        <p class="pmp-admin-subtitle"><?php _e('Manage project categories like Water Projects, Orphan Homes, etc.', 'project-map-plugin'); ?></p>
    </div>

    <?php if ($message): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error); ?></p>
        </div>
    <?php endif; ?>

    <div class="pmp-admin-content">
        <div class="pmp-admin-grid">
            <!-- Add/Edit Form -->
            <div class="pmp-admin-card">
                <div class="pmp-card-header">
                    <h2><?php echo $edit_type ? __('Edit Project Type', 'project-map-plugin') : __('Add New Project Type', 'project-map-plugin'); ?></h2>
                </div>
                <div class="pmp-card-body">
                    <form method="post" action="">
                        <?php wp_nonce_field('pmp_project_type_action', 'pmp_project_type_nonce'); ?>
                        <input type="hidden" name="action_type" value="<?php echo $edit_type ? 'edit' : 'add'; ?>">
                        <?php if ($edit_type): ?>
                            <input type="hidden" name="type_id" value="<?php echo esc_attr($edit_type->id); ?>">
                        <?php endif; ?>

                        <div class="pmp-form-group">
                            <label for="type_name"><?php _e('Name', 'project-map-plugin'); ?> <span class="required">*</span></label>
                            <input type="text"
                                   id="type_name"
                                   name="type_name"
                                   class="regular-text"
                                   value="<?php echo $edit_type ? esc_attr($edit_type->name) : ''; ?>"
                                   placeholder="<?php _e('e.g., Water Project', 'project-map-plugin'); ?>"
                                   required>
                        </div>

                        <div class="pmp-form-group">
                            <label for="type_description"><?php _e('Description', 'project-map-plugin'); ?></label>
                            <textarea id="type_description"
                                      name="type_description"
                                      rows="3"
                                      class="large-text"
                                      placeholder="<?php _e('Brief description of this project type', 'project-map-plugin'); ?>"><?php echo $edit_type ? esc_textarea($edit_type->description) : ''; ?></textarea>
                        </div>

                        <div class="pmp-form-actions">
                            <button type="submit" class="button button-primary">
                                <?php echo $edit_type ? __('Update Project Type', 'project-map-plugin') : __('Add Project Type', 'project-map-plugin'); ?>
                            </button>
                            <?php if ($edit_type): ?>
                                <a href="<?php echo admin_url('admin.php?page=pmp-project-types'); ?>" class="button">
                                    <?php _e('Cancel', 'project-map-plugin'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Project Types List -->
            <div class="pmp-admin-card pmp-admin-card-wide">
                <div class="pmp-card-header">
                    <h2><?php _e('All Project Types', 'project-map-plugin'); ?></h2>
                </div>
                <div class="pmp-card-body">
                    <?php if (empty($project_types)): ?>
                        <div class="pmp-empty-state">
                            <span class="dashicons dashicons-category"></span>
                            <p><?php _e('No project types found. Add your first project type above.', 'project-map-plugin'); ?></p>
                        </div>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th class="column-id" style="width: 50px;"><?php _e('ID', 'project-map-plugin'); ?></th>
                                    <th class="column-name"><?php _e('Name', 'project-map-plugin'); ?></th>
                                    <th class="column-description"><?php _e('Description', 'project-map-plugin'); ?></th>
                                    <th class="column-projects" style="width: 100px;"><?php _e('Projects', 'project-map-plugin'); ?></th>
                                    <th class="column-actions" style="width: 120px;"><?php _e('Actions', 'project-map-plugin'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($project_types as $type): ?>
                                    <tr>
                                        <td class="column-id">
                                            <?php echo intval($type->id); ?>
                                        </td>
                                        <td class="column-name">
                                            <strong><?php echo esc_html($type->name); ?></strong>
                                        </td>
                                        <td class="column-description">
                                            <?php echo esc_html($type->description ?: '—'); ?>
                                        </td>
                                        <td class="column-projects">
                                            <?php if ($type->project_count > 0): ?>
                                                <a href="<?php echo admin_url('admin.php?page=pmp-projects&project_type=' . $type->id); ?>" class="">
                                                    <?php echo intval($type->project_count); ?>
                                                </a>
                                            <?php else: ?>
                                                0
                                            <?php endif; ?>
                                        </td>
                                        <td class="column-actions">
                                            <a href="<?php echo admin_url('admin.php?page=pmp-project-types&edit=' . $type->id); ?>"
                                               class="button button-small">
                                                <?php _e('Edit', 'project-map-plugin'); ?>
                                            </a>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=pmp-project-types&delete=' . $type->id), 'delete_project_type_' . $type->id); ?>"
                                               class="button button-small button-link-delete"
                                               onclick="return confirm('<?php _e('Are you sure you want to delete this project type?', 'project-map-plugin'); ?>')">
                                                <?php _e('Delete', 'project-map-plugin'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Documentation -->
        <div class="pmp-admin-card pmp-admin-card-full">
            <div class="pmp-card-header">
                <h2><span class="dashicons dashicons-book" style="margin-right: 8px;"></span><?php _e('How to Use Project Type IDs', 'project-map-plugin'); ?></h2>
            </div>
            <div class="pmp-card-body">
                <div class="pmp-doc-grid">
                    <div class="pmp-doc-item">
                        <h3><?php _e('Shortcode Usage', 'project-map-plugin'); ?></h3>
                        <p><?php _e('Use the ID from the table above to filter projects by type:', 'project-map-plugin'); ?></p>
                        <table class="pmp-doc-table">
                            <tr>
                                <td><code>[project_map project_type="1"]</code></td>
                                <td><?php _e('Show only projects of type with ID 1', 'project-map-plugin'); ?></td>
                            </tr>
                            <tr>
                                <td><code>[project_map project_type="2" show_filters="false"]</code></td>
                                <td><?php _e('Show type ID 2 without filter controls', 'project-map-plugin'); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="pmp-doc-item">
                        <h3><?php _e('Tips', 'project-map-plugin'); ?></h3>
                        <ul class="pmp-doc-list">
                            <li><?php _e('The ID is automatically assigned when you create a new project type', 'project-map-plugin'); ?></li>
                            <li><?php _e('IDs remain constant even if you rename the project type', 'project-map-plugin'); ?></li>
                            <li><?php _e('Descriptions appear on individual project report pages', 'project-map-plugin'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.pmp-admin-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 20px;
    align-items: start;
}

.pmp-admin-card-wide {
    grid-column: span 1;
}

.pmp-form-row {
    display: flex;
    gap: 20px;
}

.pmp-form-half {
    flex: 1;
}

.pmp-count-badge {
    display: inline-block;
    background: #f0f0f1;
    color: #50575e;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 500;
}

.pmp-count-link {
    text-decoration: none;
    background: #2271b1;
    color: #fff;
}

.pmp-count-link:hover {
    background: #135e96;
    color: #fff;
}

.pmp-empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.pmp-empty-state .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #ccc;
    margin-bottom: 15px;
}

@media (max-width: 1200px) {
    .pmp-admin-grid {
        grid-template-columns: 1fr;
    }
}

.pmp-admin-card-full {
    grid-column: 1 / -1;
    margin-top: 10px;
}

.pmp-doc-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.pmp-doc-item h3 {
    margin: 0 0 12px 0;
    font-size: 14px;
    color: #1d2327;
}

.pmp-doc-item p {
    margin: 0 0 12px 0;
    color: #50575e;
}

.pmp-doc-table {
    width: 100%;
    border-collapse: collapse;
}

.pmp-doc-table td {
    padding: 8px 12px;
    border-bottom: 1px solid #e0e0e0;
    font-size: 13px;
}

.pmp-doc-table td:first-child {
    width: 45%;
}

.pmp-doc-table code {
    background: #f0f0f1;
    padding: 3px 6px;
    border-radius: 3px;
    font-size: 12px;
}

.pmp-doc-list {
    margin: 0;
    padding-left: 20px;
}

.pmp-doc-list li {
    margin-bottom: 8px;
    color: #50575e;
    font-size: 13px;
}

@media (max-width: 782px) {
    .pmp-doc-grid {
        grid-template-columns: 1fr;
    }
}
</style>
