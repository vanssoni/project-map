<?php
/**
 * Admin Solution Types Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table = $wpdb->prefix . 'pmp_solution_types';
$projects_table = $wpdb->prefix . 'pmp_projects';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['pmp_solution_type_nonce']) || !wp_verify_nonce($_POST['pmp_solution_type_nonce'], 'pmp_solution_type_action')) {
        wp_die(__('Security check failed.', 'project-map-plugin'));
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'add') {
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);

        if (empty($name)) {
            $message = __('Name is required.', 'project-map-plugin');
            $message_type = 'error';
        } else {
            $result = $wpdb->insert($table, array(
                'name' => $name,
                'description' => $description
            ), array('%s', '%s'));

            if ($result) {
                $message = __('Solution type added successfully.', 'project-map-plugin');
                $message_type = 'success';
            } else {
                $message = __('Error adding solution type. Name may already exist.', 'project-map-plugin');
                $message_type = 'error';
            }
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $name = sanitize_text_field($_POST['name']);
        $description = sanitize_textarea_field($_POST['description']);

        if (empty($name)) {
            $message = __('Name is required.', 'project-map-plugin');
            $message_type = 'error';
        } else {
            $result = $wpdb->update($table, array(
                'name' => $name,
                'description' => $description
            ), array('id' => $id), array('%s', '%s'), array('%d'));

            if ($result !== false) {
                $message = __('Solution type updated successfully.', 'project-map-plugin');
                $message_type = 'success';
            } else {
                $message = __('Error updating solution type.', 'project-map-plugin');
                $message_type = 'error';
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);

        // Check if any projects use this solution type
        $usage_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $projects_table WHERE solution_type_id = %d", $id
        ));

        if ($usage_count > 0) {
            $message = sprintf(__('Cannot delete: %d project(s) are using this solution type.', 'project-map-plugin'), $usage_count);
            $message_type = 'error';
        } else {
            $result = $wpdb->delete($table, array('id' => $id), array('%d'));
            if ($result) {
                $message = __('Solution type deleted successfully.', 'project-map-plugin');
                $message_type = 'success';
            } else {
                $message = __('Error deleting solution type.', 'project-map-plugin');
                $message_type = 'error';
            }
        }
    }
}

// Get all solution types with usage count
$solution_types = $wpdb->get_results(
    "SELECT st.*, COUNT(p.id) as usage_count
     FROM $table st
     LEFT JOIN $projects_table p ON st.id = p.solution_type_id
     GROUP BY st.id
     ORDER BY st.name ASC"
);

// Get editing type if in edit mode
$editing_type = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $editing_type = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $edit_id));
}
?>

<div class="wrap pmp-admin">
    <h1 class="wp-heading-inline"><?php _e('Solution Types', 'project-map-plugin'); ?></h1>
    <hr class="wp-header-end">

    <?php if ($message): ?>
        <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <div class="pmp-two-column-layout">
        <!-- Add/Edit Form -->
        <div class="pmp-form-column">
            <div class="pmp-form-section">
                <h2><?php echo $editing_type ? __('Edit Solution Type', 'project-map-plugin') : __('Add New Solution Type', 'project-map-plugin'); ?></h2>

                <form method="post">
                    <?php wp_nonce_field('pmp_solution_type_action', 'pmp_solution_type_nonce'); ?>
                    <input type="hidden" name="action" value="<?php echo $editing_type ? 'edit' : 'add'; ?>">
                    <?php if ($editing_type): ?>
                        <input type="hidden" name="id" value="<?php echo $editing_type->id; ?>">
                    <?php endif; ?>

                    <div class="pmp-form-field">
                        <label for="name"><?php _e('Name', 'project-map-plugin'); ?> <span class="required">*</span></label>
                        <input type="text" name="name" id="name" class="regular-text" required
                               value="<?php echo $editing_type ? esc_attr($editing_type->name) : ''; ?>"
                               placeholder="<?php _e('e.g., Piped System Tap Stand', 'project-map-plugin'); ?>">
                    </div>

                    <div class="pmp-form-field">
                        <label for="description"><?php _e('Description', 'project-map-plugin'); ?></label>
                        <textarea name="description" id="description" rows="4" class="large-text"
                                  placeholder="<?php _e('Optional description...', 'project-map-plugin'); ?>"><?php echo $editing_type ? esc_textarea($editing_type->description) : ''; ?></textarea>
                    </div>

                    <div class="pmp-form-actions">
                        <button type="submit" class="button button-primary">
                            <?php echo $editing_type ? __('Update Solution Type', 'project-map-plugin') : __('Add Solution Type', 'project-map-plugin'); ?>
                        </button>
                        <?php if ($editing_type): ?>
                            <a href="<?php echo admin_url('admin.php?page=pmp-solution-types'); ?>" class="button"><?php _e('Cancel', 'project-map-plugin'); ?></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Solution Types List -->
        <div class="pmp-list-column">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-id" style="width: 50px;"><?php _e('ID', 'project-map-plugin'); ?></th>
                        <th class="column-name"><?php _e('Name', 'project-map-plugin'); ?></th>
                        <th class="column-description"><?php _e('Description', 'project-map-plugin'); ?></th>
                        <th class="column-count"><?php _e('Projects', 'project-map-plugin'); ?></th>
                        <th class="column-actions"><?php _e('Actions', 'project-map-plugin'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($solution_types)): ?>
                        <tr>
                            <td colspan="5" class="pmp-no-items"><?php _e('No solution types found.', 'project-map-plugin'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($solution_types as $type): ?>
                            <tr>
                                <td class="column-id">
                                    <?php echo intval($type->id); ?>
                                </td>
                                <td class="column-name">
                                    <strong><?php echo esc_html($type->name); ?></strong>
                                </td>
                                <td class="column-description">
                                    <?php echo esc_html($type->description ?: '-'); ?>
                                </td>
                                <td class="column-count">
                                    <?php if ($type->usage_count > 0): ?>
                                        <a href="<?php echo admin_url('admin.php?page=pmp-projects&solution_type=' . $type->id); ?>">
                                            <?php echo number_format($type->usage_count); ?>
                                        </a>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                                <td class="column-actions">
                                    <a href="<?php echo admin_url('admin.php?page=pmp-solution-types&edit=' . $type->id); ?>" class="button button-small"><?php _e('Edit', 'project-map-plugin'); ?></a>
                                    <?php if ($type->usage_count == 0): ?>
                                        <form method="post" style="display:inline;">
                                            <?php wp_nonce_field('pmp_solution_type_action', 'pmp_solution_type_nonce'); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $type->id; ?>">
                                            <button type="submit" class="button button-small button-link-delete"
                                                    onclick="return confirm('<?php _e('Are you sure you want to delete this solution type?', 'project-map-plugin'); ?>')">
                                                <?php _e('Delete', 'project-map-plugin'); ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Documentation -->
        <div class="pmp-documentation-section">
            <h2><span class="dashicons dashicons-book"></span> <?php _e('How to Use Solution Type IDs', 'project-map-plugin'); ?></h2>
            <div class="pmp-doc-grid">
                <div class="pmp-doc-item">
                    <h3><?php _e('Shortcode Usage', 'project-map-plugin'); ?></h3>
                    <p><?php _e('Use the ID from the table above to filter projects by solution type:', 'project-map-plugin'); ?></p>
                    <table class="pmp-doc-table">
                        <tr>
                            <td><code>[project_map solution_type="1"]</code></td>
                            <td><?php _e('Show only projects with solution type ID 1', 'project-map-plugin'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[project_map solution_type="3" country="India"]</code></td>
                            <td><?php _e('Combine with country filter', 'project-map-plugin'); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="pmp-doc-item">
                    <h3><?php _e('Tips', 'project-map-plugin'); ?></h3>
                    <ul class="pmp-doc-list">
                        <li><?php _e('The ID is automatically assigned when you create a new solution type', 'project-map-plugin'); ?></li>
                        <li><?php _e('IDs remain constant even if you rename the solution type', 'project-map-plugin'); ?></li>
                        <li><?php _e('Descriptions appear on individual project report pages', 'project-map-plugin'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.pmp-documentation-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    margin-top: 20px;
    grid-column: 1 / -1;
}

.pmp-documentation-section h2 {
    margin: 0 0 20px 0;
    padding: 0;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.pmp-documentation-section h2 .dashicons {
    color: #2271b1;
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
