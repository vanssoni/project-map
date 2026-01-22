<?php
/**
 * Admin Import/Export Page
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$projects_table = $wpdb->prefix . 'pmp_projects';
$total_projects = $wpdb->get_var("SELECT COUNT(*) FROM $projects_table");
?>

<div class="wrap pmp-admin">
    <h1><?php _e('Import / Export', 'project-map-plugin'); ?></h1>
    <hr class="wp-header-end">

    <!-- Projects Import/Export - Side by Side -->
    <div class="pmp-ie-row">
        <!-- Import Projects -->
        <div class="pmp-ie-card">
            <div class="pmp-ie-card-header pmp-ie-import-header">
                <span class="dashicons dashicons-upload"></span>
                <h2><?php _e('Import Projects', 'project-map-plugin'); ?></h2>
            </div>
            <div class="pmp-ie-card-body">
                <p class="pmp-ie-description"><?php _e('Upload a CSV file to import projects. New project types and solution types will be created automatically.', 'project-map-plugin'); ?></p>

                <form id="csv-import-form" enctype="multipart/form-data">
                    <div class="pmp-ie-file-input">
                        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                        <p class="description"><?php _e('Max size: 10MB', 'project-map-plugin'); ?></p>
                    </div>

                    <div class="pmp-ie-buttons">
                        <button type="submit" class="button button-primary" id="import-submit">
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e('Import', 'project-map-plugin'); ?>
                        </button>
                        <button type="button" id="download-sample-csv" class="button">
                            <span class="dashicons dashicons-media-spreadsheet"></span>
                            <?php _e('Sample CSV', 'project-map-plugin'); ?>
                        </button>
                    </div>
                </form>

                <div id="import-progress" style="display: none; margin-top: 15px;">
                    <div class="pmp-progress-bar"><div class="pmp-progress-fill"></div></div>
                    <p id="progress-text"><?php _e('Importing...', 'project-map-plugin'); ?></p>
                </div>

                <div id="import-results" style="display: none; margin-top: 15px;">
                    <div id="results-content"></div>
                </div>
            </div>
        </div>

        <!-- Export Projects -->
        <div class="pmp-ie-card">
            <div class="pmp-ie-card-header pmp-ie-export-header">
                <span class="dashicons dashicons-download"></span>
                <h2><?php _e('Export Projects', 'project-map-plugin'); ?></h2>
            </div>
            <div class="pmp-ie-card-body">
                <p class="pmp-ie-description"><?php _e('Download all projects as a CSV file including coordinates, types, images, and more.', 'project-map-plugin'); ?></p>

                <div class="pmp-ie-stat-box">
                    <span class="pmp-ie-stat-number"><?php echo number_format($total_projects); ?></span>
                    <span class="pmp-ie-stat-label"><?php _e('projects ready to export', 'project-map-plugin'); ?></span>
                </div>

                <div class="pmp-ie-buttons">
                    <button type="button" id="export-csv" class="button button-primary" <?php echo $total_projects == 0 ? 'disabled' : ''; ?>>
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export to CSV', 'project-map-plugin'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- CSV Format Info -->
    <div class="pmp-ie-info-section">
        <h3><span class="dashicons dashicons-info"></span> <?php _e('CSV Format', 'project-map-plugin'); ?></h3>
        <div class="pmp-csv-columns">
            <code>Village Name, Project Number, Country, GPS Latitude, GPS Longitude, Project Type, Solution Type, Completion Month, Completion Year, Beneficiaries, In Honour Of, Description, Featured Image URL, Gallery Images, Video URLs, Status</code>
        </div>
        <p><strong><?php _e('Required fields:', 'project-map-plugin'); ?></strong> Village Name, Country, GPS Latitude, GPS Longitude</p>
    </div>

    <!-- Project Types & Solution Types Import/Export -->
    <div class="pmp-ie-row pmp-ie-row-small">
        <!-- Project Types -->
        <div class="pmp-ie-card pmp-ie-card-compact">
            <div class="pmp-ie-card-header">
                <span class="dashicons dashicons-category"></span>
                <h2><?php _e('Project Types', 'project-map-plugin'); ?></h2>
            </div>
            <div class="pmp-ie-card-body">
                <div class="pmp-ie-compact-row">
                    <form id="project-types-import-form" enctype="multipart/form-data" class="pmp-ie-inline-form">
                        <input type="file" name="csv_file" id="project_types_csv_file" accept=".csv" required>
                        <button type="submit" class="button button-small"><?php _e('Import', 'project-map-plugin'); ?></button>
                    </form>
                    <button type="button" id="export-project-types" class="button button-small">
                        <span class="dashicons dashicons-download"></span> <?php _e('Export', 'project-map-plugin'); ?>
                    </button>
                </div>
                <p class="description"><?php _e('CSV Format: Name, Description', 'project-map-plugin'); ?></p>
                <div id="project-types-import-results" style="display: none;"></div>
            </div>
        </div>

        <!-- Solution Types -->
        <div class="pmp-ie-card pmp-ie-card-compact">
            <div class="pmp-ie-card-header">
                <span class="dashicons dashicons-tag"></span>
                <h2><?php _e('Solution Types', 'project-map-plugin'); ?></h2>
            </div>
            <div class="pmp-ie-card-body">
                <div class="pmp-ie-compact-row">
                    <form id="solution-types-import-form" enctype="multipart/form-data" class="pmp-ie-inline-form">
                        <input type="file" name="csv_file" id="solution_types_csv_file" accept=".csv" required>
                        <button type="submit" class="button button-small"><?php _e('Import', 'project-map-plugin'); ?></button>
                    </form>
                    <button type="button" id="export-solution-types" class="button button-small">
                        <span class="dashicons dashicons-download"></span> <?php _e('Export', 'project-map-plugin'); ?>
                    </button>
                </div>
                <p class="description"><?php _e('CSV Format: Name, Description', 'project-map-plugin'); ?></p>
                <div id="solution-types-import-results" style="display: none;"></div>
            </div>
        </div>
    </div>
</div>

<style>
/* Import/Export Page Styles */
.pmp-ie-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.pmp-ie-row-small {
    margin-top: 20px;
}

.pmp-ie-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    overflow: hidden;
}

.pmp-ie-card-header {
    background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
    color: #fff;
    padding: 15px 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.pmp-ie-card-header .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.pmp-ie-card-header h2 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #fff;
}

.pmp-ie-import-header {
    background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
}

.pmp-ie-export-header {
    background: linear-gradient(135deg, #00a32a 0%, #007017 100%);
}

.pmp-ie-card-body {
    padding: 20px;
}

.pmp-ie-description {
    color: #50575e;
    margin: 0 0 15px 0;
    font-size: 13px;
    line-height: 1.5;
}

.pmp-ie-file-input {
    margin-bottom: 15px;
}

.pmp-ie-file-input input[type="file"] {
    width: 100%;
    padding: 10px;
    border: 2px dashed #c3c4c7;
    border-radius: 4px;
    background: #f6f7f7;
    cursor: pointer;
}

.pmp-ie-file-input input[type="file"]:hover {
    border-color: #2271b1;
}

.pmp-ie-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.pmp-ie-buttons .button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.pmp-ie-buttons .button .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.pmp-ie-stat-box {
    background: linear-gradient(135deg, #f0f6fc 0%, #e8f0fe 100%);
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    padding: 25px;
    text-align: center;
    margin-bottom: 15px;
}

.pmp-ie-stat-number {
    display: block;
    font-size: 42px;
    font-weight: 700;
    color: #00a32a;
    line-height: 1;
}

.pmp-ie-stat-label {
    display: block;
    font-size: 13px;
    color: #50575e;
    margin-top: 8px;
}

.pmp-ie-info-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.pmp-ie-info-section h3 {
    margin: 0 0 15px 0;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.pmp-ie-info-section h3 .dashicons {
    color: #2271b1;
}

.pmp-csv-columns {
    background: #f6f7f7;
    padding: 12px 15px;
    border-radius: 4px;
    margin-bottom: 10px;
    overflow-x: auto;
}

.pmp-csv-columns code {
    font-size: 12px;
    white-space: nowrap;
}

.pmp-ie-card-compact .pmp-ie-card-header {
    background: #50575e;
    padding: 12px 15px;
}

.pmp-ie-card-compact .pmp-ie-card-header h2 {
    font-size: 14px;
}

.pmp-ie-card-compact .pmp-ie-card-body {
    padding: 15px;
}

.pmp-ie-compact-row {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.pmp-ie-inline-form {
    display: flex;
    gap: 8px;
    align-items: center;
    flex: 1;
}

.pmp-ie-inline-form input[type="file"] {
    flex: 1;
    min-width: 150px;
}

@media (max-width: 1024px) {
    .pmp-ie-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 600px) {
    .pmp-ie-compact-row {
        flex-direction: column;
        align-items: stretch;
    }

    .pmp-ie-inline-form {
        flex-direction: column;
    }
}
</style>
