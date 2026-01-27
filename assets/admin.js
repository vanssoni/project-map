/**
 * Project Map Plugin - Admin JavaScript
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // Initialize Select2 for searchable dropdowns
        initSelect2();

        // Initialize media uploader
        initMediaUploader();

        // Initialize delete functionality
        initDeleteProject();

        // Initialize import/export
        initImportExport();

        // Initialize popup style preview
        initPopupStylePreview();
    });

    /**
     * Initialize Select2 for searchable dropdowns
     */
    function initSelect2() {
        if (typeof $.fn.select2 === 'undefined') {
            return;
        }

        // Country dropdown
        if ($('#country').length) {
            $('#country').select2({
                placeholder: 'Search or select country...',
                allowClear: true,
                width: '100%'
            });
        }

        // Project Type dropdown
        if ($('#project_type_id').length) {
            $('#project_type_id').select2({
                placeholder: 'Select Project Type',
                allowClear: true,
                width: '100%'
            });
        }

        // Solution Type dropdown
        if ($('#solution_type_id').length) {
            $('#solution_type_id').select2({
                placeholder: 'Select Solution Type',
                allowClear: true,
                width: '100%'
            });
        }
    }

    /**
     * Media Uploader for Featured Image and Gallery
     */
    function initMediaUploader() {
        var featuredFrame, galleryFrame;

        // Featured Image Upload
        $('#upload-featured-image').on('click', function (e) {
            e.preventDefault();

            if (featuredFrame) {
                featuredFrame.open();
                return;
            }

            featuredFrame = wp.media({
                title: 'Select Featured Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });

            featuredFrame.on('select', function () {
                var attachment = featuredFrame.state().get('selection').first().toJSON();
                $('#featured_image_id').val(attachment.id);
                $('#featured-image-preview').show().find('img').attr('src', attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url);
                $('#upload-featured-image').hide();
            });

            featuredFrame.open();
        });

        // Remove Featured Image
        $('#remove-featured-image').on('click', function (e) {
            e.preventDefault();
            $('#featured_image_id').val('');
            $('#featured-image-preview').hide();
            $('#upload-featured-image').show();
        });

        // Gallery Images Upload
        $('#upload-gallery-images').on('click', function (e) {
            e.preventDefault();

            if (galleryFrame) {
                galleryFrame.open();
                return;
            }

            galleryFrame = wp.media({
                title: 'Select Gallery Images',
                button: {
                    text: 'Add to gallery'
                },
                multiple: true
            });

            galleryFrame.on('select', function () {
                var attachments = galleryFrame.state().get('selection').toJSON();
                var currentIds = $('#gallery_images').val() ? $('#gallery_images').val().split(',') : [];
                var preview = $('#gallery-preview');

                attachments.forEach(function (attachment) {
                    if (currentIds.indexOf(attachment.id.toString()) === -1) {
                        currentIds.push(attachment.id);
                        var imgUrl = attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                        preview.append(
                            '<div class="pmp-gallery-item" data-id="' + attachment.id + '">' +
                            '<img src="' + imgUrl + '" alt="">' +
                            '<button type="button" class="pmp-remove-gallery-image"><span class="dashicons dashicons-no-alt"></span></button>' +
                            '</div>'
                        );
                    }
                });

                $('#gallery_images').val(currentIds.join(','));
            });

            galleryFrame.open();
        });

        // Remove Gallery Image
        $(document).on('click', '.pmp-remove-gallery-image', function (e) {
            e.preventDefault();
            var $item = $(this).closest('.pmp-gallery-item');
            var id = $item.data('id').toString();
            var currentIds = $('#gallery_images').val().split(',');
            var newIds = currentIds.filter(function (item) {
                return item !== id;
            });
            $('#gallery_images').val(newIds.join(','));
            $item.remove();
        });
    }

    /**
     * Delete Project
     */
    function initDeleteProject() {
        $('.pmp-delete-project').on('click', function (e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
                return;
            }

            var $button = $(this);
            var $row = $button.closest('tr');
            var projectId = $button.data('id');

            $button.prop('disabled', true).text('Deleting...');

            $.ajax({
                url: pmp_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'pmp_delete_project',
                    nonce: pmp_admin_ajax.nonce,
                    id: projectId
                },
                success: function (response) {
                    if (response.success) {
                        $row.fadeOut(400, function () {
                            $(this).remove();
                        });
                    } else {
                        alert('Error: ' + response.data);
                        $button.prop('disabled', false).text('Delete');
                    }
                },
                error: function () {
                    alert('Server error. Please try again.');
                    $button.prop('disabled', false).text('Delete');
                }
            });
        });
    }

    /**
     * Import/Export Functionality
     */
    function initImportExport() {
        // Download Sample CSV
        $('#download-sample-csv').on('click', function (e) {
            e.preventDefault();

            var csvContent = 'Village Name,Project Number,Country,GPS Latitude,GPS Longitude,Project Type,Solution Type,Completion Month,Completion Year,Beneficiaries,In Honour Of,Description,Featured Image URL,Gallery Images,Video URLs,Status\n';
            csvContent += 'La Canada Village,PRJ-001,Honduras,14.9914,-88.05018,Water Projects,Piped System Tap Stand,5,2012,810,Lucia\'s Gift,A clean water project serving 810 people in the rural community.,https://example.com/image1.jpg,"https://example.com/gallery1.jpg,https://example.com/gallery2.jpg",https://youtube.com/watch?v=example,publish\n';
            csvContent += 'Rajasthan Village,PRJ-002,India,26.9124,73.0243,Water Projects,Well With Hand Pump,12,2014,1650,Anonymous Donors,Providing clean water access to 1650 beneficiaries.,,,,draft\n';

            var blob = new Blob([csvContent], { type: 'text/csv' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = 'sample-projects.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
        });

        // Import CSV
        $('#csv-import-form').on('submit', function (e) {
            e.preventDefault();

            var fileInput = $('#csv_file')[0];
            if (!fileInput.files.length) {
                alert('Please select a CSV file');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'pmp_import_csv');
            formData.append('nonce', pmp_admin_ajax.nonce);
            formData.append('csv_file', fileInput.files[0]);

            $('#import-progress').show();
            $('#import-results').hide();
            $('#import-submit').prop('disabled', true);

            // Animate progress bar
            var $progressFill = $('.pmp-progress-fill');
            $progressFill.css('width', '30%');

            $.ajax({
                url: pmp_admin_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    $progressFill.css('width', '100%');

                    setTimeout(function () {
                        $('#import-progress').hide();
                        $('#import-submit').prop('disabled', false);

                        if (response.success) {
                            var html = '<div class="notice notice-success"><p>';
                            html += 'Successfully imported <strong>' + response.data.imported + '</strong> projects.';
                            html += '</p></div>';

                            if (response.data.errors.length > 0) {
                                html += '<div class="notice notice-warning"><p><strong>Errors encountered:</strong></p><ul>';
                                response.data.errors.forEach(function (error) {
                                    html += '<li>' + error + '</li>';
                                });
                                html += '</ul></div>';
                            }

                            $('#results-content').html(html);
                            $('#import-results').show();
                            $('#csv_file').val('');

                        } else {
                            $('#results-content').html('<div class="notice notice-error"><p>Import failed: ' + response.data + '</p></div>');
                            $('#import-results').show();
                        }
                    }, 500);
                },
                error: function () {
                    $('#import-progress').hide();
                    $('#import-submit').prop('disabled', false);
                    $('#results-content').html('<div class="notice notice-error"><p>Import failed: Server error</p></div>');
                    $('#import-results').show();
                }
            });
        });

        // Export CSV
        $('#export-csv').on('click', function (e) {
            e.preventDefault();

            var $button = $(this);
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Exporting...');

            $.ajax({
                url: pmp_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'pmp_export_csv',
                    nonce: pmp_admin_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        var csvContent = '';
                        response.data.data.forEach(function (row) {
                            csvContent += row.map(function (cell) {
                                // Escape quotes and wrap in quotes if contains comma
                                if (cell === null || cell === undefined) cell = '';
                                cell = cell.toString();
                                if (cell.indexOf(',') !== -1 || cell.indexOf('"') !== -1 || cell.indexOf('\n') !== -1) {
                                    cell = '"' + cell.replace(/"/g, '""') + '"';
                                }
                                return cell;
                            }).join(',') + '\n';
                        });

                        var blob = new Blob([csvContent], { type: 'text/csv' });
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = 'projects-export-' + new Date().toISOString().slice(0, 10) + '.csv';
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);

                    } else {
                        alert('Export failed: ' + response.data);
                    }

                    $button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Export to CSV');
                },
                error: function () {
                    alert('Export failed: Server error');
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Export to CSV');
                }
            });
        });

        // Import Project Types
        $('#project-types-import-form').on('submit', function (e) {
            e.preventDefault();
            handleImport('project_types_csv_file', 'pmp_import_project_types', 'project-types-import-results');
        });

        // Export Project Types
        $('#export-project-types').on('click', function (e) {
            e.preventDefault();
            handleExport('pmp_export_project_types', 'project-types-export-' + new Date().toISOString().slice(0, 10) + '.csv', $(this));
        });

        // Import Solution Types
        $('#solution-types-import-form').on('submit', function (e) {
            e.preventDefault();
            handleImport('solution_types_csv_file', 'pmp_import_solution_types', 'solution-types-import-results');
        });

        // Export Solution Types
        $('#export-solution-types').on('click', function (e) {
            e.preventDefault();
            handleExport('pmp_export_solution_types', 'solution-types-export-' + new Date().toISOString().slice(0, 10) + '.csv', $(this));
        });
    }

    /**
     * Handle Import
     */
    function handleImport(fileInputId, action, resultsId) {
        var fileInput = $('#' + fileInputId)[0];
        if (!fileInput.files.length) {
            alert('Please select a CSV file');
            return;
        }

        var formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', pmp_admin_ajax.nonce);
        formData.append('csv_file', fileInput.files[0]);

        $('#' + resultsId).hide();

        $.ajax({
            url: pmp_admin_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    var html = '<div class="notice notice-success"><p>';
                    html += 'Successfully imported <strong>' + response.data.imported + '</strong> items.';
                    html += '</p></div>';

                    if (response.data.errors.length > 0) {
                        html += '<div class="notice notice-warning"><p><strong>Errors encountered:</strong></p><ul>';
                        response.data.errors.forEach(function (error) {
                            html += '<li>' + error + '</li>';
                        });
                        html += '</ul></div>';
                    }

                    $('#' + resultsId).html(html).show();
                    $('#' + fileInputId).val('');
                } else {
                    $('#' + resultsId).html('<div class="notice notice-error"><p>Import failed: ' + response.data + '</p></div>').show();
                }
            },
            error: function () {
                $('#' + resultsId).html('<div class="notice notice-error"><p>Import failed: Server error</p></div>').show();
            }
        });
    }

    /**
     * Handle Export
     */
    function handleExport(action, filename, $button) {
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Exporting...');

        $.ajax({
            url: pmp_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: action,
                nonce: pmp_admin_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    var csvContent = '';
                    response.data.data.forEach(function (row) {
                        csvContent += row.map(function (cell) {
                            if (cell === null || cell === undefined) cell = '';
                            cell = cell.toString();
                            if (cell.indexOf(',') !== -1 || cell.indexOf('"') !== -1 || cell.indexOf('\n') !== -1) {
                                cell = '"' + cell.replace(/"/g, '""') + '"';
                            }
                            return cell;
                        }).join(',') + '\n';
                    });

                    var blob = new Blob([csvContent], { type: 'text/csv' });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                } else {
                    alert('Export failed: ' + response.data);
                }

                $button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> ' + $button.text().replace('Exporting...', '').trim());
            },
            error: function () {
                alert('Export failed: Server error');
                $button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> ' + $button.text().replace('Exporting...', '').trim());
            }
        });
    }

    /**
     * Popup Style Preview Selection
     */
    function initPopupStylePreview() {
        $('.pmp-popup-preview-item').on('click', function () {
            var style = $(this).data('style');
            $('.pmp-popup-preview-item').removeClass('active');
            $(this).addClass('active');
            $('#popup_style').val(style);
        });
    }

})(jQuery);
