<?php
/**
 * Admin Projects List Page
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$projects_table = $wpdb->prefix . 'pmp_projects';
$types_table = $wpdb->prefix . 'pmp_solution_types';
$project_types_table = $wpdb->prefix . 'pmp_project_types';
$countries_table = $wpdb->prefix . 'pmp_countries';

// Pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Search and filters
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$country_filter = isset($_GET['country']) ? sanitize_text_field($_GET['country']) : '';
$project_type_filter = isset($_GET['project_type']) ? intval($_GET['project_type']) : 0;
$type_filter = isset($_GET['solution_type']) ? intval($_GET['solution_type']) : 0;
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// Build query
$where = "WHERE 1=1";
$params = array();

if ($search) {
    $where .= " AND (p.village_name LIKE %s OR p.project_number LIKE %s OR p.country LIKE %s)";
    $search_term = '%' . $wpdb->esc_like($search) . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($country_filter) {
    $where .= " AND p.country = %s";
    $params[] = $country_filter;
}

if ($project_type_filter) {
    $where .= " AND p.project_type_id = %d";
    $params[] = $project_type_filter;
}

if ($type_filter) {
    $where .= " AND p.solution_type_id = %d";
    $params[] = $type_filter;
}

if ($status_filter) {
    $where .= " AND p.status = %s";
    $params[] = $status_filter;
}

// Get total count
$count_sql = "SELECT COUNT(*) FROM $projects_table p $where";
if (!empty($params)) {
    $total_items = $wpdb->get_var($wpdb->prepare($count_sql, $params));
} else {
    $total_items = $wpdb->get_var($count_sql);
}

// Get projects
$sql = "SELECT p.*, st.name as solution_type_name, pt.name as project_type_name, pt.icon as project_type_icon, c.flag as country_flag
        FROM $projects_table p
        LEFT JOIN $types_table st ON p.solution_type_id = st.id
        LEFT JOIN $project_types_table pt ON p.project_type_id = pt.id
        LEFT JOIN $countries_table c ON p.country = c.name
        $where
        ORDER BY p.created_at DESC
        LIMIT %d OFFSET %d";

$params[] = $per_page;
$params[] = $offset;

$projects = $wpdb->get_results($wpdb->prepare($sql, $params));

// Get project types for filter
$project_types = ProjectMapPlugin::get_project_types();

// Get solution types for filter
$solution_types = ProjectMapPlugin::get_solution_types();

// Get countries with projects for filter
$countries = $wpdb->get_col("SELECT DISTINCT country FROM $projects_table ORDER BY country ASC");

// Get stats
$stats = $wpdb->get_row("SELECT COUNT(*) as total, SUM(beneficiaries) as beneficiaries FROM $projects_table WHERE status = 'publish'");
?>

<div class="wrap pmp-admin">
    <h1 class="wp-heading-inline"><?php _e('Projects', 'project-map-plugin'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=pmp-add-project'); ?>" class="page-title-action"><?php _e('Add New', 'project-map-plugin'); ?></a>
    <a href="<?php echo admin_url('admin.php?page=pmp-import-export'); ?>" class="page-title-action"><?php _e('Import CSV', 'project-map-plugin'); ?></a>
    <hr class="wp-header-end">

    <!-- Statistics Cards -->
    <div class="pmp-stats-row">
        <div class="pmp-stat-card">
            <div class="pmp-stat-icon"><span class="dashicons dashicons-location-alt"></span></div>
            <div class="pmp-stat-content">
                <div class="pmp-stat-number"><?php echo number_format($stats->total); ?></div>
                <div class="pmp-stat-label"><?php _e('Total Projects', 'project-map-plugin'); ?></div>
            </div>
        </div>
        <div class="pmp-stat-card">
            <div class="pmp-stat-icon"><span class="dashicons dashicons-groups"></span></div>
            <div class="pmp-stat-content">
                <div class="pmp-stat-number"><?php echo number_format($stats->beneficiaries); ?></div>
                <div class="pmp-stat-label"><?php _e('People Served', 'project-map-plugin'); ?></div>
            </div>
        </div>
        <div class="pmp-stat-card">
            <div class="pmp-stat-icon"><span class="dashicons dashicons-admin-site"></span></div>
            <div class="pmp-stat-content">
                <div class="pmp-stat-number"><?php echo count($countries); ?></div>
                <div class="pmp-stat-label"><?php _e('Countries', 'project-map-plugin'); ?></div>
            </div>
        </div>
    </div>

    <!-- Shortcode Information Box -->
    <div class="pmp-info-box">
        <h3><span class="dashicons dashicons-shortcode"></span> <?php _e('Shortcode Usage', 'project-map-plugin'); ?></h3>
        <div class="pmp-info-content">
            <p><?php _e('Use the following shortcodes to display the project map:', 'project-map-plugin'); ?></p>
            <table class="pmp-shortcode-table">
                <tr>
                    <td><code>[project_map]</code></td>
                    <td><?php _e('Display all projects on the map', 'project-map-plugin'); ?></td>
                </tr>
                <tr>
                    <td><code>[project_map country="India"]</code></td>
                    <td><?php _e('Display projects filtered by country', 'project-map-plugin'); ?></td>
                </tr>
                <tr>
                    <td><code>[project_map solution_type="1"]</code></td>
                    <td><?php _e('Display projects filtered by solution type ID', 'project-map-plugin'); ?></td>
                </tr>
                <tr>
                    <td><code>[project_map show_filters="false"]</code></td>
                    <td><?php _e('Hide filter dropdowns', 'project-map-plugin'); ?></td>
                </tr>
                <tr>
                    <td><code>[project_map height="600px"]</code></td>
                    <td><?php _e('Set custom map height', 'project-map-plugin'); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Filters -->
    <div class="pmp-filters-bar">
        <form method="get" class="pmp-filter-form">
            <input type="hidden" name="page" value="pmp-projects">

            <div class="pmp-filter-group">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php _e('Search projects...', 'project-map-plugin'); ?>" class="pmp-search-input">
            </div>

            <div class="pmp-filter-group">
                <select name="country" class="pmp-filter-select">
                    <option value=""><?php _e('All Countries', 'project-map-plugin'); ?></option>
                    <?php foreach ($countries as $country): ?>
                        <option value="<?php echo esc_attr($country); ?>" <?php selected($country_filter, $country); ?>><?php echo esc_html($country); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pmp-filter-group">
                <select name="project_type" class="pmp-filter-select">
                    <option value=""><?php _e('All Project Types', 'project-map-plugin'); ?></option>
                    <?php foreach ($project_types as $type): ?>
                        <option value="<?php echo esc_attr($type->id); ?>" <?php selected($project_type_filter, $type->id); ?>>
                            <?php echo esc_html(($type->icon ? $type->icon . ' ' : '') . $type->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pmp-filter-group">
                <select name="solution_type" class="pmp-filter-select">
                    <option value=""><?php _e('All Solution Types', 'project-map-plugin'); ?></option>
                    <?php foreach ($solution_types as $type): ?>
                        <option value="<?php echo esc_attr($type->id); ?>" <?php selected($type_filter, $type->id); ?>><?php echo esc_html($type->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pmp-filter-group">
                <select name="status" class="pmp-filter-select">
                    <option value=""><?php _e('All Statuses', 'project-map-plugin'); ?></option>
                    <option value="publish" <?php selected($status_filter, 'publish'); ?>><?php _e('Published', 'project-map-plugin'); ?></option>
                    <option value="draft" <?php selected($status_filter, 'draft'); ?>><?php _e('Draft', 'project-map-plugin'); ?></option>
                </select>
            </div>

            <div class="pmp-filter-group">
                <button type="submit" class="button"><?php _e('Filter', 'project-map-plugin'); ?></button>
                <?php if ($search || $country_filter || $project_type_filter || $type_filter || $status_filter): ?>
                    <a href="<?php echo admin_url('admin.php?page=pmp-projects'); ?>" class="button"><?php _e('Reset', 'project-map-plugin'); ?></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Projects Table -->
    <table class="wp-list-table widefat fixed striped pmp-projects-table">
        <thead>
            <tr>
                <th class="column-image"><?php _e('Image', 'project-map-plugin'); ?></th>
                <th class="column-title"><?php _e('Project', 'project-map-plugin'); ?></th>
                <th class="column-country"><?php _e('Country', 'project-map-plugin'); ?></th>
                <th class="column-project-type"><?php _e('Project Type', 'project-map-plugin'); ?></th>
                <th class="column-type"><?php _e('Solution Type', 'project-map-plugin'); ?></th>
                <th class="column-beneficiaries"><?php _e('Beneficiaries', 'project-map-plugin'); ?></th>
                <th class="column-status"><?php _e('Status', 'project-map-plugin'); ?></th>
                <th class="column-actions"><?php _e('Actions', 'project-map-plugin'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($projects)): ?>
                <tr>
                    <td colspan="9" class="pmp-no-items">
                        <?php _e('No projects found.', 'project-map-plugin'); ?>
                        <a href="<?php echo admin_url('admin.php?page=pmp-add-project'); ?>"><?php _e('Add your first project', 'project-map-plugin'); ?></a>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($projects as $project):
                    $image_url = $project->featured_image_id ? wp_get_attachment_image_url($project->featured_image_id, 'thumbnail') : '';
                    $months = array(1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec');
                    $completion = $project->completion_year ? ($project->completion_month ? $months[$project->completion_month] . ' ' : '') . $project->completion_year : '-';
                ?>
                    <tr data-id="<?php echo $project->id; ?>">
                        <td class="column-image">
                            <?php if ($image_url): ?>
                                <img src="<?php echo esc_url($image_url); ?>" alt="" class="pmp-project-thumb">
                            <?php else: ?>
                                <div class="pmp-project-thumb-placeholder"><span class="dashicons dashicons-format-image"></span></div>
                            <?php endif; ?>
                        </td>
                        <td class="column-title">
                            <strong><a href="<?php echo admin_url('admin.php?page=pmp-add-project&id=' . $project->id); ?>"><?php echo esc_html($project->village_name); ?></a></strong>
                            <?php if ($project->project_number): ?>
                                <br><span class="pmp-project-number">#<?php echo esc_html($project->project_number); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="column-country">
                            <?php if ($project->country_flag): ?>
                                <span class="pmp-country-flag"><?php echo $project->country_flag; ?></span>
                            <?php endif; ?>
                            <?php echo esc_html($project->country); ?>
                        </td>
                        <td class="column-project-type">
                            <?php if ($project->project_type_name): ?>
                                <?php if ($project->project_type_icon): ?>
                                    <span class="pmp-type-icon"><?php echo $project->project_type_icon; ?></span>
                                <?php endif; ?>
                                <?php echo esc_html($project->project_type_name); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="column-type"><?php echo esc_html($project->solution_type_name ?: '-'); ?></td>
                        <td class="column-beneficiaries"><?php echo number_format($project->beneficiaries); ?></td>
                        <td class="column-status">
                            <span class="pmp-status-badge pmp-status-<?php echo esc_attr($project->status); ?>">
                                <?php echo $project->status === 'publish' ? __('Published', 'project-map-plugin') : __('Draft', 'project-map-plugin'); ?>
                            </span>
                        </td>
                        <td class="column-actions">
                            <a href="<?php echo admin_url('admin.php?page=pmp-add-project&id=' . $project->id); ?>" class="button button-small"><?php _e('Edit', 'project-map-plugin'); ?></a>
                            <button class="button button-small button-link-delete pmp-delete-project" data-id="<?php echo $project->id; ?>"><?php _e('Delete', 'project-map-plugin'); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_items > $per_page): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo sprintf(_n('%s item', '%s items', $total_items, 'project-map-plugin'), number_format($total_items)); ?></span>
                <?php
                $page_links = paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => ceil($total_items / $per_page),
                    'current' => $current_page
                ));
                if ($page_links) {
                    echo '<span class="pagination-links">' . $page_links . '</span>';
                }
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
