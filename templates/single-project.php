<?php
/**
 * Single Project Report Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get project from global variable set by handle_single_project
global $pmp_project;
if (!isset($pmp_project) || !$pmp_project) {
    $project_id = get_query_var('pmp_project_id');
    $pmp_project = ProjectMapPlugin::get_project($project_id);
}

if (!$pmp_project || $pmp_project->status !== 'publish') {
    wp_redirect(home_url());
    exit;
}

$project = $pmp_project;

// Get featured image
$featured_image = $project->featured_image_id ? wp_get_attachment_url($project->featured_image_id) : '';

// Get gallery images
$gallery_images = array();
if ($project->gallery_images) {
    $gallery_ids = explode(',', $project->gallery_images);
    foreach ($gallery_ids as $img_id) {
        $img_url = wp_get_attachment_url(intval($img_id));
        if ($img_url) {
            $gallery_images[] = $img_url;
        }
    }
}

// Get video URLs
$video_urls = $project->video_urls ? array_filter(explode("\n", $project->video_urls)) : array();

// Format completion date
$months = array(
    1 => 'January',
    2 => 'February',
    3 => 'March',
    4 => 'April',
    5 => 'May',
    6 => 'June',
    7 => 'July',
    8 => 'August',
    9 => 'September',
    10 => 'October',
    11 => 'November',
    12 => 'December'
);
$completion_date = '';
if ($project->completion_year) {
    $completion_date = $project->completion_month ? $months[$project->completion_month] . ' ' . $project->completion_year : $project->completion_year;
}

// Get Mapbox token and check if Mapbox is enabled
$enable_mapbox = get_option('pmp_enable_mapbox', '0');
$mapbox_token = get_option('pmp_mapbox_token', '');
$use_mapbox = ($enable_mapbox == '1' && !empty($mapbox_token));

// Color customization
$header_bg_color = get_option('pmp_header_bg_color', '#1d1d1d');
$header_text_color = get_option('pmp_header_text_color', '#ffffff');
$accent_color = get_option('pmp_accent_color', '#ffc220');
$button_text_color = get_option('pmp_button_text_color', '#2d2d2d');

// Get type descriptions
$project_type_desc = $project->project_type_description ?? '';
$solution_type_desc = $project->solution_type_description ?? '';

// Use theme's header
get_header();
?>

<!-- Swiper CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

<style>
    /* Override theme containers for full-page layout */
    body.pmp-single-project-page {
        margin: 0 !important;
        padding: 0 !important;
    }

    body.pmp-single-project-page .site,
    body.pmp-single-project-page .site-content,
    body.pmp-single-project-page .content-area,
    body.pmp-single-project-page .site-main,
    body.pmp-single-project-page .container,
    body.pmp-single-project-page .wrap,
    body.pmp-single-project-page .wrapper,
    body.pmp-single-project-page #main,
    body.pmp-single-project-page #content,
    body.pmp-single-project-page .main-content {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Ensure header doesn't interfere */
    body.pmp-single-project-page header,
    body.pmp-single-project-page .site-header {
        position: relative;
        z-index: 1000;
    }

    /* Ensure footer doesn't interfere */
    body.pmp-single-project-page footer,
    body.pmp-single-project-page .site-footer {
        position: relative;
        z-index: 100;
    }
</style>

<div class="pmp-single-project-page"
    style="--pmp-header-bg-color: <?php echo esc_attr($header_bg_color); ?>; --pmp-header-text-color: <?php echo esc_attr($header_text_color); ?>; --pmp-accent-color: <?php echo esc_attr($accent_color); ?>; --pmp-button-text-color: <?php echo esc_attr($button_text_color); ?>;">

    <!-- Map Section (Full Viewport) -->
    <section class="pmp-map-section">
        <div id="pmp-detail-map" class="pmp-detail-map-container"></div>

        <!-- Back to Map Button -->
        <a href="javascript:history.back()" class="pmp-back-to-map-btn" onclick="event.preventDefault(); window.history.length > 1 ? history.back() : window.location.href='<?php echo home_url(); ?>';">
            <span class="pmp-back-icon">←</span>
            <span class="pmp-back-text"><?php _e('Back to Map', 'project-map-plugin'); ?></span>
        </a>

        <!-- Project Header Overlay -->
        <div class="pmp-project-header">
            <div class="pmp-project-marker">📍</div>
            <h1 class="pmp-project-title"><?php echo esc_html($project->village_name); ?></h1>
            <div class="pmp-project-location">
                <?php if ($project->country_flag): ?>
                    <span class="pmp-project-flag"><?php echo $project->country_flag; ?></span>
                <?php endif; ?>
                <span class="pmp-project-country"><?php echo esc_html($project->country); ?></span>
                <span class="pmp-project-coords">
                    © <?php echo number_format($project->gps_latitude, 5); ?>,
                    <?php echo number_format($project->gps_longitude, 5); ?> ©
                </span>
            </div>
        </div>

        <!-- Info Card -->
        <div class="pmp-info-card">
            <div class="pmp-info-card-label"><?php _e('PEOPLE SERVED', 'project-map-plugin'); ?></div>
            <div class="pmp-info-card-value"><?php echo number_format($project->beneficiaries); ?></div>
        </div>

        <!-- Scroll Indicator -->
        <div class="pmp-scroll-indicator">
            <div class="pmp-scroll-arrow">↓</div>
        </div>

        <!-- Share Button -->
        <button class="pmp-share-button" id="pmp-share-button">
            <span class="pmp-share-icon">🔗</span>
            <?php _e('SHARE', 'project-map-plugin'); ?>
        </button>
    </section>

    <!-- Content Section -->
    <section class="pmp-content-section">
        <div class="pmp-content-wrapper">

            <?php if ($featured_image): ?>
                <!-- Project Image -->
                <div class="pmp-project-image-container">
                    <img src="<?php echo esc_url($featured_image); ?>"
                        alt="<?php echo esc_attr($project->village_name); ?>">
                    <?php if ($project->solution_type_name): ?>
                        <p class="pmp-image-caption">
                            <?php printf(__('Photos of the %s in %s', 'project-map-plugin'), esc_html($project->solution_type_name), esc_html($project->village_name)); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Project Info Cards -->
            <div class="pmp-info-cards">
                <!-- Main Stat Card -->
                <div class="pmp-info-main-card">
                    <div class="pmp-info-main-icon">👥</div>
                    <div class="pmp-info-main-content">
                        <div class="pmp-info-main-label"><?php _e('PEOPLE SERVED', 'project-map-plugin'); ?></div>
                        <div class="pmp-info-main-value"><?php echo number_format($project->beneficiaries); ?></div>
                    </div>
                </div>

                <!-- Info Grid -->
                <div class="pmp-info-grid">
                    <div class="pmp-info-item">
                        <div class="pmp-info-icon">❤️</div>
                        <div class="pmp-info-content">
                            <div class="pmp-info-label"><?php _e('THANKS TO', 'project-map-plugin'); ?></div>
                            <div class="pmp-info-value"><?php echo esc_html($project->in_honour_of ?: __('Anonymous Donors', 'project-map-plugin')); ?></div>
                        </div>
                    </div>

                    <div class="pmp-info-item">
                        <div class="pmp-info-icon">📋</div>
                        <div class="pmp-info-content">
                            <div class="pmp-info-label"><?php _e('PROJECT TYPE', 'project-map-plugin'); ?></div>
                            <div class="pmp-info-value"><?php echo esc_html($project->project_type_name ?: __('N/A', 'project-map-plugin')); ?></div>
                        </div>
                    </div>

                    <div class="pmp-info-item">
                        <div class="pmp-info-icon">⚙️</div>
                        <div class="pmp-info-content">
                            <div class="pmp-info-label"><?php _e('SOLUTION TYPE', 'project-map-plugin'); ?></div>
                            <div class="pmp-info-value"><?php echo esc_html($project->solution_type_name ?: __('N/A', 'project-map-plugin')); ?></div>
                        </div>
                    </div>

                    <div class="pmp-info-item">
                        <div class="pmp-info-icon">📅</div>
                        <div class="pmp-info-content">
                            <div class="pmp-info-label"><?php _e('COMPLETED', 'project-map-plugin'); ?></div>
                            <div class="pmp-info-value"><?php echo esc_html($completion_date ?: __('N/A', 'project-map-plugin')); ?></div>
                        </div>
                    </div>

                    <?php if ($project->project_number): ?>
                    <div class="pmp-info-item">
                        <div class="pmp-info-icon">#️⃣</div>
                        <div class="pmp-info-content">
                            <div class="pmp-info-label"><?php _e('PROJECT NUMBER', 'project-map-plugin'); ?></div>
                            <div class="pmp-info-value"><?php echo esc_html($project->project_number); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="pmp-info-item">
                        <div class="pmp-info-icon">📍</div>
                        <div class="pmp-info-content">
                            <div class="pmp-info-label"><?php _e('LOCATION', 'project-map-plugin'); ?></div>
                            <div class="pmp-info-value"><?php echo esc_html($project->village_name . ', ' . $project->country); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($project->description): ?>
                <!-- Project Description -->
                <div class="pmp-project-description">
                    <h2><?php _e('About This Project', 'project-map-plugin'); ?></h2>
                    <div class="pmp-description-content">
                        <?php echo wp_kses_post($project->description); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($project_type_desc || $solution_type_desc): ?>
                <!-- Type Descriptions -->
                <div class="pmp-type-descriptions">
                    <?php if ($project_type_desc): ?>
                        <div class="pmp-type-description-item">
                            <h3><?php _e('About', 'project-map-plugin'); ?>         <?php echo esc_html($project->project_type_name); ?>
                            </h3>
                            <p><?php echo esc_html($project_type_desc); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($solution_type_desc): ?>
                        <div class="pmp-type-description-item">
                            <h3><?php _e('About', 'project-map-plugin'); ?>
                                <?php echo esc_html($project->solution_type_name); ?></h3>
                            <p><?php echo esc_html($solution_type_desc); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($gallery_images)): ?>
                <!-- Image Gallery -->
                <div class="pmp-project-gallery">
                    <h2><?php _e('Photo Gallery', 'project-map-plugin'); ?></h2>

                    <!-- Desktop Grid Gallery -->
                    <div class="pmp-gallery-grid pmp-gallery-desktop">
                        <?php foreach ($gallery_images as $image): ?>
                            <div class="pmp-gallery-item">
                                <img src="<?php echo esc_url($image); ?>" alt="" loading="lazy">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Mobile Slider Gallery (Swiper) -->
                    <div class="pmp-gallery-slider pmp-gallery-mobile">
                        <div class="swiper pmp-gallery-swiper">
                            <div class="swiper-wrapper">
                                <?php foreach ($gallery_images as $index => $image): ?>
                                    <div class="swiper-slide">
                                        <img src="<?php echo esc_url($image); ?>" alt="" loading="lazy">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="swiper-pagination"></div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                        </div>
                        <div class="pmp-gallery-counter">
                            <span class="pmp-current-slide">1</span> / <span class="pmp-total-slides"><?php echo count($gallery_images); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($video_urls)): ?>
                <!-- Videos -->
                <div class="pmp-project-videos">
                    <h2><?php _e('Videos', 'project-map-plugin'); ?></h2>
                    <div class="pmp-videos-grid">
                        <?php foreach ($video_urls as $video_url):
                            // Convert YouTube URL to embed format
                            $embed_url = $video_url;
                            if (strpos($video_url, 'youtube.com/watch') !== false) {
                                parse_str(parse_url($video_url, PHP_URL_QUERY), $params);
                                if (isset($params['v'])) {
                                    $embed_url = 'https://www.youtube.com/embed/' . $params['v'];
                                }
                            } elseif (strpos($video_url, 'youtu.be/') !== false) {
                                $video_id = substr(parse_url($video_url, PHP_URL_PATH), 1);
                                $embed_url = 'https://www.youtube.com/embed/' . $video_id;
                            } elseif (strpos($video_url, 'vimeo.com/') !== false) {
                                $video_id = substr(parse_url($video_url, PHP_URL_PATH), 1);
                                $embed_url = 'https://player.vimeo.com/video/' . $video_id;
                            }
                            ?>
                            <div class="pmp-video-item">
                                <iframe src="<?php echo esc_url($embed_url); ?>" frameborder="0" allowfullscreen></iframe>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>

</div>

<script>
    (function () {
        'use strict';

        function initMap() {
            var mapContainer = document.getElementById('pmp-detail-map');
            if (!mapContainer) {
                console.error('Map container not found');
                return;
            }

            <?php if ($use_mapbox): ?>
                // Initialize Mapbox 3D Map with satellite-streets style
                if (typeof mapboxgl === 'undefined') {
                    console.error('Mapbox GL JS not loaded');
                    return;
                }

                mapboxgl.accessToken = '<?php echo esc_js($mapbox_token); ?>';

                var map = new mapboxgl.Map({
                    container: 'pmp-detail-map',
                    style: 'mapbox://styles/mapbox/satellite-streets-v12',
                    center: [<?php echo $project->gps_longitude; ?>, <?php echo $project->gps_latitude; ?>],
                    zoom: 14,
                    pitch: 45,
                    bearing: -15,
                    antialias: true,
                    interactive: false
                });

                map.on('load', function () {
                    // Add 3D terrain
                    map.addSource('mapbox-dem', {
                        'type': 'raster-dem',
                        'url': 'mapbox://mapbox.mapbox-terrain-dem-v1',
                        'tileSize': 512,
                        'maxzoom': 14
                    });

                    map.setTerrain({ 'source': 'mapbox-dem', 'exaggeration': 1.5 });

                    // Add 3D building extrusion
                    var layers = map.getStyle().layers;
                    var labelLayerId;
                    for (var i = 0; i < layers.length; i++) {
                        if (layers[i].type === 'symbol' && layers[i].layout['text-field']) {
                            labelLayerId = layers[i].id;
                            break;
                        }
                    }

                    map.addLayer({
                        'id': '3d-buildings',
                        'source': 'composite',
                        'source-layer': 'building',
                        'filter': ['==', 'extrude', 'true'],
                        'type': 'fill-extrusion',
                        'minzoom': 15,
                        'paint': {
                            'fill-extrusion-color': '#aaa',
                            'fill-extrusion-height': ['get', 'height'],
                            'fill-extrusion-base': ['get', 'min_height'],
                            'fill-extrusion-opacity': 0.6
                        }
                    }, labelLayerId);

                    // Add sky atmosphere
                    map.addLayer({
                        'id': 'sky',
                        'type': 'sky',
                        'paint': {
                            'sky-type': 'atmosphere',
                            'sky-atmosphere-sun': [0.0, 90.0],
                            'sky-atmosphere-sun-intensity': 15
                        }
                    });

                    // Smooth flyTo animation with tilted camera
                    setTimeout(function () {
                        map.flyTo({
                            center: [<?php echo $project->gps_longitude; ?>, <?php echo $project->gps_latitude; ?>],
                            zoom: 17,
                            pitch: 65,
                            bearing: 30,
                            duration: 5000,
                            essential: true,
                            curve: 1.5,
                            easing: function(t) {
                                return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
                            }
                        });

                        // Start smooth rotation after fly-in
                        setTimeout(function () {
                            var rotationAngle = 30;
                            function rotate() {
                                rotationAngle += 0.08;
                                if (rotationAngle >= 360) rotationAngle = 0;
                                map.rotateTo(rotationAngle, { duration: 0 });
                                requestAnimationFrame(rotate);
                            }
                            rotate();
                        }, 5500);
                    }, 800);
                });

                map.on('error', function (e) {
                    console.error('Mapbox error:', e);
                });
            <?php else: ?>
                // Initialize Leaflet Map (OpenStreetMap)
                if (typeof L === 'undefined') {
                    console.error('Leaflet not loaded. Please check if Leaflet library is properly enqueued.');
                    // Try to load Leaflet dynamically as fallback
                    var leafletCSS = document.createElement('link');
                    leafletCSS.rel = 'stylesheet';
                    leafletCSS.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                    document.head.appendChild(leafletCSS);

                    var leafletJS = document.createElement('script');
                    leafletJS.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                    leafletJS.onload = function () {
                        initLeafletMap();
                    };
                    document.head.appendChild(leafletJS);
                    return;
                }

                initLeafletMap();

                function initLeafletMap() {
                    if (typeof L === 'undefined') {
                        console.error('Leaflet still not available after loading');
                        return;
                    }

                    var accentColor = getComputedStyle(document.documentElement).getPropertyValue('--pmp-accent-color') || '#ffc220';

                    var map = L.map('pmp-detail-map', {
                        center: [<?php echo $project->gps_latitude; ?>, <?php echo $project->gps_longitude; ?>],
                        zoom: 12,
                        zoomControl: false,
                        dragging: false,
                        touchZoom: false,
                        doubleClickZoom: false,
                        scrollWheelZoom: false,
                        boxZoom: false,
                        keyboard: false
                    });

                    // Add OpenStreetMap tiles
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors',
                        maxZoom: 19,
                        tileSize: 256,
                        zoomOffset: 0
                    }).addTo(map);

                    // Add marker with custom icon
                    var markerIcon = L.divIcon({
                        className: 'pmp-leaflet-marker',
                        html: '<div style="background-color: ' + accentColor + '; width: 20px; height: 20px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.3);"></div>',
                        iconSize: [20, 20],
                        iconAnchor: [10, 10]
                    });

                    L.marker([<?php echo $project->gps_latitude; ?>, <?php echo $project->gps_longitude; ?>], {
                        icon: markerIcon
                    }).addTo(map);

                    // Animate zoom in
                    setTimeout(function () {
                        map.setView([<?php echo $project->gps_latitude; ?>, <?php echo $project->gps_longitude; ?>], 16, {
                            animate: true,
                            duration: 2.0
                        });
                    }, 500);
                }
            <?php endif; ?>
        }

        // Wait for DOM and map libraries to be ready
        function waitForMapLibrary() {
            <?php if ($use_mapbox): ?>
                if (typeof mapboxgl !== 'undefined') {
                    initMap();
                } else {
                    setTimeout(waitForMapLibrary, 100);
                }
            <?php else: ?>
                if (typeof L !== 'undefined') {
                    initMap();
                } else {
                    setTimeout(waitForMapLibrary, 100);
                }
            <?php endif; ?>
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                setTimeout(waitForMapLibrary, 100);
            });
        } else {
            setTimeout(waitForMapLibrary, 100);
        }
    })();

    // Scroll indicator
    document.querySelector('.pmp-scroll-indicator').addEventListener('click', function () {
        window.scrollTo({
            top: window.innerHeight,
            behavior: 'smooth'
        });
    });

    // Share button
    document.getElementById('pmp-share-button').addEventListener('click', function () {
        if (navigator.share) {
            navigator.share({
                title: '<?php echo esc_js($project->village_name); ?>',
                text: '<?php printf(esc_js(__('Check out this water project in %s!', 'project-map-plugin')), esc_js($project->country)); ?>',
                url: window.location.href
            });
        } else {
            // Fallback: copy to clipboard
            navigator.clipboard.writeText(window.location.href).then(function () {
                alert('<?php echo esc_js(__('Link copied to clipboard!', 'project-map-plugin')); ?>');
            });
        }
    });
</script>

<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
    // Initialize Swiper Gallery
    document.addEventListener('DOMContentLoaded', function() {
        var gallerySwiper = document.querySelector('.pmp-gallery-swiper');
        if (gallerySwiper) {
            var swiper = new Swiper('.pmp-gallery-swiper', {
                slidesPerView: 1,
                spaceBetween: 15,
                loop: true,
                grabCursor: true,
                pagination: {
                    el: '.swiper-pagination',
                    clickable: true,
                    dynamicBullets: true
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev'
                },
                on: {
                    slideChange: function() {
                        var currentSlide = document.querySelector('.pmp-current-slide');
                        if (currentSlide) {
                            currentSlide.textContent = this.realIndex + 1;
                        }
                    }
                }
            });
        }
    });
</script>

<?php
// Use theme's footer
get_footer();
?>