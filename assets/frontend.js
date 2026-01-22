/**
 * Project Map Plugin - Frontend JavaScript
 */

(function () {
    'use strict';

    // Global variables
    var map;
    var projectsData = [];
    var markerClusterGroup = null; // For Leaflet MarkerCluster
    var isInitialLoad = true; // Track if this is the first load
    var currentFilter = {
        country: '',
        projectType: 0,
        solutionType: 0,
        search: ''
    };

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        var mapWrapper = document.querySelector('.pmp-map-wrapper');
        if (!mapWrapper) return;

        // Get initial filters from data attributes
        currentFilter.country = mapWrapper.dataset.country || '';
        currentFilter.projectType = parseInt(mapWrapper.dataset.projectType) || 0;
        currentFilter.solutionType = parseInt(mapWrapper.dataset.solutionType) || 0;

        // Check if Mapbox should be used
        if (pmp_ajax.use_mapbox && typeof mapboxgl !== 'undefined') {
            // Initialize Mapbox
            mapboxgl.accessToken = pmp_ajax.mapbox_token;
            initializeMapbox();
        } else if (typeof L !== 'undefined') {
            // Initialize Leaflet (OpenStreetMap)
            initializeLeaflet();
        } else {
            console.error('Project Map Plugin: No map library available. Please check your settings.');
            hideLoadingScreen();
            return;
        }

        setupEventListeners();
        loadProjects();
    });

    /**
     * Initialize Mapbox Map
     */
    function initializeMapbox() {
        var mapContainer = document.getElementById('pmp-map');
        if (!mapContainer) return;

        map = new mapboxgl.Map({
            container: 'pmp-map',
            style: 'mapbox://styles/mapbox/' + pmp_ajax.map_style,
            projection: 'mercator',
            center: [0, 20],
            zoom: 2,
            minZoom: 1,
            maxZoom: 18
        });

        // Add navigation controls
        map.addControl(new mapboxgl.NavigationControl(), 'bottom-right');

        map.on('load', function () {
            hideLoadingScreen();
            addMarkersToMap();
        });
    }

    /**
     * Initialize Leaflet Map (OpenStreetMap)
     */
    function initializeLeaflet() {
        var mapContainer = document.getElementById('pmp-map');
        if (!mapContainer) return;

        map = L.map('pmp-map', {
            center: [20, 0],
            zoom: 2,
            minZoom: 1,
            maxZoom: 18,
            zoomControl: false // Disable default zoom control, we add our own at bottom-right
        });

        // Get tile layer based on OSM style setting
        var osmStyle = pmp_ajax.osm_style || 'standard';
        var tileLayer = getOsmTileLayer(osmStyle);
        tileLayer.addTo(map);

        // Add navigation controls
        L.control.zoom({
            position: 'bottomright'
        }).addTo(map);

        map.whenReady(function () {
            hideLoadingScreen();
            addMarkersToMapLeaflet();
        });
    }

    /**
     * Get OSM tile layer based on style
     */
    function getOsmTileLayer(style) {
        var layers = {
            'standard': {
                url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                attribution: '© OpenStreetMap contributors'
            },
            'humanitarian': {
                url: 'https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png',
                attribution: '© OpenStreetMap contributors, Tiles: Humanitarian OSM Team'
            },
            'topo': {
                url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
                attribution: '© OpenStreetMap contributors, SRTM | © OpenTopoMap'
            },
            'dark': {
                url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
                attribution: '© OpenStreetMap contributors, © CARTO'
            },
            'light': {
                url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
                attribution: '© OpenStreetMap contributors, © CARTO'
            }
        };

        var config = layers[style] || layers['standard'];
        return L.tileLayer(config.url, {
            attribution: config.attribution,
            maxZoom: 19,
            subdomains: 'abc'
        });
    }

    /**
     * Load projects via AJAX
     */
    function loadProjects() {
        var formData = new FormData();
        formData.append('action', 'pmp_get_projects');
        formData.append('nonce', pmp_ajax.nonce);
        formData.append('country', currentFilter.country);
        formData.append('project_type', currentFilter.projectType);
        formData.append('solution_type', currentFilter.solutionType);
        formData.append('search', currentFilter.search);

        fetch(pmp_ajax.ajax_url, {
            method: 'POST',
            body: formData
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.success) {
                    projectsData = data.data.projects;
                    updateStatistics(data.data.stats);

                    if (pmp_ajax.use_mapbox) {
                        addMarkersToMap();
                    } else {
                        addMarkersToMapLeaflet();
                    }

                    // Only auto-center when filters change, not on initial load
                    // Initial load shows global view
                    if (!isInitialLoad) {
                        if (projectsData.length > 0) {
                            fitMapToBounds();
                        } else {
                            resetMapView();
                        }
                    }
                    isInitialLoad = false;
                }
            })
            .catch(function (error) {
                console.error('Project Map Plugin: Error loading projects', error);
            });
    }

    /**
     * Add clustered markers to Mapbox map
     */
    function addMarkersToMap() {
        if (!map || !pmp_ajax.use_mapbox) return;
        // Remove existing source and layers
        if (map.getLayer('clusters')) map.removeLayer('clusters');
        if (map.getLayer('cluster-count')) map.removeLayer('cluster-count');
        if (map.getLayer('unclustered-point')) map.removeLayer('unclustered-point');
        if (map.getSource('projects')) map.removeSource('projects');

        if (projectsData.length === 0) return;

        // Prepare GeoJSON
        var geojson = {
            type: 'FeatureCollection',
            features: projectsData.map(function (project) {
                return {
                    type: 'Feature',
                    geometry: {
                        type: 'Point',
                        coordinates: project.coordinates
                    },
                    properties: {
                        id: project.id,
                        name: project.name,
                        country: project.country,
                        countryFlag: project.countryFlag,
                        peopleServed: project.peopleServed,
                        date: project.date,
                        image: project.image,
                        solutionType: project.solutionType,
                        fundedBy: project.fundedBy
                    }
                };
            })
        };

        // Add source
        map.addSource('projects', {
            type: 'geojson',
            data: geojson,
            cluster: true,
            clusterMaxZoom: 14,
            clusterRadius: parseInt(pmp_ajax.cluster_radius) || 50
        });

        // Get marker color from settings (accent color)
        var markerColor = pmp_ajax.accent_color || '#ffc220';
        var markerTextColor = pmp_ajax.button_text_color || '#2d2d2d';

        // Add cluster circles
        map.addLayer({
            id: 'clusters',
            type: 'circle',
            source: 'projects',
            filter: ['has', 'point_count'],
            paint: {
                'circle-color': markerColor,
                'circle-radius': [
                    'step',
                    ['get', 'point_count'],
                    25, 10, 30, 100, 40
                ],
                'circle-stroke-width': 0,
                'circle-opacity': 1
            }
        });

        // Add cluster count labels
        map.addLayer({
            id: 'cluster-count',
            type: 'symbol',
            source: 'projects',
            filter: ['has', 'point_count'],
            layout: {
                'text-field': ['get', 'point_count_abbreviated'],
                'text-font': ['DIN Offc Pro Medium', 'Arial Unicode MS Bold'],
                'text-size': 14
            },
            paint: {
                'text-color': markerTextColor
            }
        });

        // Add individual markers
        map.addLayer({
            id: 'unclustered-point',
            type: 'circle',
            source: 'projects',
            filter: ['!', ['has', 'point_count']],
            paint: {
                'circle-color': markerColor,
                'circle-radius': 10,
                'circle-stroke-width': 3,
                'circle-stroke-color': '#fff',
                'circle-opacity': 1
            }
        });

        // Click handlers
        map.on('click', 'clusters', onClusterClick);
        map.on('click', 'unclustered-point', onMarkerClick);

        // Cursor change on hover
        map.on('mouseenter', 'clusters', function () {
            map.getCanvas().style.cursor = 'pointer';
        });
        map.on('mouseleave', 'clusters', function () {
            map.getCanvas().style.cursor = '';
        });
        map.on('mouseenter', 'unclustered-point', function () {
            map.getCanvas().style.cursor = 'pointer';
        });
        map.on('mouseleave', 'unclustered-point', function () {
            map.getCanvas().style.cursor = '';
        });
    }

    /**
     * Handle cluster click - zoom in
     */
    function onClusterClick(e) {
        var features = map.queryRenderedFeatures(e.point, { layers: ['clusters'] });
        var clusterId = features[0].properties.cluster_id;

        map.getSource('projects').getClusterExpansionZoom(clusterId, function (err, zoom) {
            if (err) return;

            map.easeTo({
                center: features[0].geometry.coordinates,
                zoom: zoom
            });
        });
    }

    /**
     * Handle marker click - show popup
     */
    function onMarkerClick(e) {
        var coordinates = e.features[0].geometry.coordinates.slice();
        var props = e.features[0].properties;

        // Show preview tooltip
        showMarkerPopup(props, coordinates);
    }

    /**
     * Show marker preview popup
     */
    function showMarkerPopup(props, coordinates) {
        var accentColor = pmp_ajax.accent_color || '#ffc220';
        var buttonTextColor = pmp_ajax.button_text_color || '#2d2d2d';
        var projectImage = props.image || pmp_ajax.placeholder_image || '';

        var popupHTML = '<div class="pmp-marker-preview">' +
            '<button class="pmp-marker-popup-close" onclick="pmpCloseMarkerPopup()">✕</button>' +
            '<img class="pmp-marker-project-image" src="' + projectImage + '" alt="' + props.name + '" onerror="this.src=\'' + (pmp_ajax.placeholder_image || '') + '\'">' +
            '<h3>' + props.name + '</h3>' +
            '<p class="pmp-marker-country"><span class="pmp-marker-flag">' + props.countryFlag + '</span> ' + props.country + '</p>' +
            '<p class="pmp-marker-served">' + parseInt(props.peopleServed).toLocaleString() + ' people served</p>' +
            '<button onclick="pmpOpenProjectPopup(\'' + props.id + '\')" style="background-color: ' + accentColor + '; color: ' + buttonTextColor + ';">VIEW DETAILS</button>' +
            '</div>';

        if (pmp_ajax.use_mapbox) {
            // Close existing Mapbox popups
            var existingPopups = document.getElementsByClassName('mapboxgl-popup');
            while (existingPopups.length > 0) {
                existingPopups[0].remove();
            }

            var popup = new mapboxgl.Popup({
                offset: [0, -15],
                closeButton: false,
                maxWidth: '240px',
                anchor: 'bottom'
            })
                .setLngLat(coordinates)
                .setHTML(popupHTML)
                .addTo(map);

            // Pan map to ensure popup is visible
            map.easeTo({
                center: coordinates,
                padding: { top: 150, bottom: 50, left: 50, right: 50 },
                duration: 300
            });
        } else {
            // Close existing Leaflet popups
            map.closePopup();

            L.popup({
                maxWidth: 240,
                minWidth: 220,
                closeButton: false,
                offset: [0, -15],
                autoPan: true,
                autoPanPadding: [80, 80],
                autoPanPaddingTopLeft: [50, 120],
                autoPanPaddingBottomRight: [50, 50]
            })
                .setLatLng([coordinates[1], coordinates[0]])
                .setContent(popupHTML)
                .openOn(map);
        }
    }

    /**
     * Open project detail popup
     */
    window.pmpOpenProjectPopup = function (projectId) {
        var project = projectsData.find(function (p) { return p.id == projectId; });
        if (!project) return;

        // Populate popup content
        document.getElementById('pmp-popup-title').textContent = project.name;
        document.getElementById('pmp-popup-flag').textContent = project.countryFlag;
        document.getElementById('pmp-popup-country').textContent = project.country;
        document.getElementById('pmp-popup-coordinates').textContent =
            project.coordinates[1].toFixed(4) + ', ' + project.coordinates[0].toFixed(4);

        // Handle image with placeholder fallback
        var popupImage = document.getElementById('pmp-popup-image');
        var projectImage = project.image || pmp_ajax.placeholder_image || '';
        popupImage.src = projectImage;
        popupImage.onerror = function() {
            if (pmp_ajax.placeholder_image) {
                this.src = pmp_ajax.placeholder_image;
            }
        };
        document.getElementById('pmp-popup-people-served').textContent = project.peopleServed.toLocaleString();
        document.getElementById('pmp-popup-funded-by').textContent = project.fundedBy;
        document.getElementById('pmp-popup-solution-type').textContent = project.solutionType || 'N/A';
        document.getElementById('pmp-popup-date').textContent = project.date || 'N/A';

        // Set view report link
        document.getElementById('pmp-view-report-button').href = pmp_ajax.project_base_url + project.id;

        // Show popup and overlay
        var popupElement = document.getElementById('pmp-project-popup');
        var popupContent = document.querySelector('.pmp-popup-content');
        popupElement.classList.add('active');
        document.getElementById('pmp-popup-overlay').classList.add('active');

        // Prevent scroll propagation on popup content
        if (popupContent) {
            popupContent.addEventListener('wheel', function (e) {
                e.stopPropagation();
            }, { passive: false });
            popupContent.addEventListener('touchmove', function (e) {
                e.stopPropagation();
            }, { passive: false });
        }

        // Close existing map popups
        if (pmp_ajax.use_mapbox) {
            var mapPopups = document.getElementsByClassName('mapboxgl-popup');
            while (mapPopups.length > 0) {
                mapPopups[0].remove();
            }
        } else {
            if (map && typeof map.closePopup === 'function') {
                map.closePopup();
            }
        }

        // Fly to project (only if map exists)
        if (map) {
            if (pmp_ajax.use_mapbox) {
                map.flyTo({
                    center: project.coordinates,
                    zoom: 12,
                    duration: 2000
                });
            } else {
                // Leaflet
                map.setView([project.coordinates[1], project.coordinates[0]], 12, {
                    animate: true,
                    duration: 1.0
                });
            }
        }
    };

    /**
     * Close project popup
     */
    function closeProjectPopup() {
        document.getElementById('pmp-project-popup').classList.remove('active');
        document.getElementById('pmp-popup-overlay').classList.remove('active');
    }

    /**
     * Close marker popup (small preview popup on map)
     */
    window.pmpCloseMarkerPopup = function () {
        if (pmp_ajax.use_mapbox) {
            var existingPopups = document.getElementsByClassName('mapboxgl-popup');
            while (existingPopups.length > 0) {
                existingPopups[0].remove();
            }
        } else {
            if (map && typeof map.closePopup === 'function') {
                map.closePopup();
            }
        }
    };

    /**
     * Close all popups (both marker preview and detail sidebar)
     */
    function closeAllPopups() {
        closeProjectPopup();
        window.pmpCloseMarkerPopup();
    }

    /**
     * Update statistics display
     */
    function updateStatistics(stats) {
        var peopleEl = document.getElementById('pmp-stat-people');
        var projectsEl = document.getElementById('pmp-stat-projects');
        var countriesEl = document.getElementById('pmp-stat-countries');

        if (peopleEl) animateCounter(peopleEl, stats.totalBeneficiaries);
        if (projectsEl) animateCounter(projectsEl, stats.totalProjects);
        if (countriesEl) animateCounter(countriesEl, stats.totalCountries);
    }

    /**
     * Animate counter value
     */
    function animateCounter(element, targetValue) {
        var startValue = 0;
        var duration = 2000;
        var startTime = performance.now();

        function update(currentTime) {
            var elapsed = currentTime - startTime;
            var progress = Math.min(elapsed / duration, 1);
            var easeProgress = 1 - Math.pow(1 - progress, 3);
            var currentValue = Math.floor(startValue + (targetValue - startValue) * easeProgress);

            element.textContent = currentValue.toLocaleString();

            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                element.textContent = targetValue.toLocaleString();
            }
        }

        requestAnimationFrame(update);
    }

    /**
     * Add markers to Leaflet map using MarkerCluster for performance (optimized for 10,000+ projects)
     */
    function addMarkersToMapLeaflet() {
        if (!map || pmp_ajax.use_mapbox) return;

        // Remove existing cluster group or markers
        if (markerClusterGroup) {
            map.removeLayer(markerClusterGroup);
            markerClusterGroup = null;
        }

        // Also clear any individual markers (fallback cleanup)
        map.eachLayer(function (layer) {
            if (layer instanceof L.Marker) {
                map.removeLayer(layer);
            }
        });

        if (projectsData.length === 0) return;

        var markerColor = pmp_ajax.accent_color || '#ffc220';
        var accentColor = pmp_ajax.accent_color || '#ffc220';
        var buttonTextColor = pmp_ajax.button_text_color || '#2d2d2d';

        // Check if MarkerCluster is available, fallback to simple markers if not
        if (typeof L.markerClusterGroup !== 'function') {
            console.warn('Leaflet.markercluster not loaded, using simple markers');
            addSimpleMarkersLeaflet(markerColor, accentColor, buttonTextColor);
            return;
        }

        // Create marker cluster group with optimized settings for large datasets
        markerClusterGroup = L.markerClusterGroup({
            chunkedLoading: true, // Load markers in chunks for better performance
            chunkInterval: 200,   // Interval between chunks
            chunkDelay: 50,       // Delay between chunk processing
            spiderfyOnMaxZoom: true,
            showCoverageOnHover: false,
            zoomToBoundsOnClick: true,
            maxClusterRadius: parseInt(pmp_ajax.cluster_radius) || 50,
            disableClusteringAtZoom: 16,
            // Custom cluster icon
            iconCreateFunction: function(cluster) {
                var count = cluster.getChildCount();
                var size = count < 10 ? 'small' : count < 100 ? 'medium' : 'large';
                var dimension = size === 'small' ? 36 : size === 'medium' ? 44 : 52;
                var fontSize = size === 'small' ? 12 : size === 'medium' ? 14 : 16;

                return L.divIcon({
                    html: '<div style="background-color: ' + markerColor + '; color: ' + buttonTextColor + '; width: ' + dimension + 'px; height: ' + dimension + 'px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: ' + fontSize + 'px; box-shadow: 0 3px 10px rgba(0,0,0,0.3); border: 3px solid #fff;">' + count + '</div>',
                    className: 'pmp-cluster-icon',
                    iconSize: [dimension, dimension]
                });
            }
        });

        // Create markers in batch for better performance
        var markers = [];

        projectsData.forEach(function (project) {
            var marker = L.marker([project.coordinates[1], project.coordinates[0]], {
                icon: L.divIcon({
                    className: 'pmp-leaflet-marker',
                    html: '<div style="background-color: ' + markerColor + '; width: 20px; height: 20px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.4); cursor: pointer;"></div>',
                    iconSize: [20, 20],
                    iconAnchor: [10, 10]
                })
            });

            // Store project data on marker for lazy popup creation
            marker.projectData = project;

            // Lazy load popup content on click for better performance
            marker.on('click', function(e) {
                var proj = this.projectData;
                var projectImage = proj.image || pmp_ajax.placeholder_image || '';

                var popupHTML = '<div class="pmp-marker-preview">' +
                    '<button class="pmp-marker-popup-close" onclick="pmpCloseMarkerPopup()">✕</button>' +
                    '<img class="pmp-marker-project-image" src="' + projectImage + '" alt="' + proj.name + '" onerror="this.src=\'' + (pmp_ajax.placeholder_image || '') + '\'">' +
                    '<h3>' + proj.name + '</h3>' +
                    '<p class="pmp-marker-country"><span class="pmp-marker-flag">' + (proj.countryFlag || '') + '</span> ' + proj.country + '</p>' +
                    '<p class="pmp-marker-served">' + parseInt(proj.peopleServed).toLocaleString() + ' people served</p>' +
                    '<button onclick="pmpOpenProjectPopup(\'' + proj.id + '\')" style="background-color: ' + accentColor + '; color: ' + buttonTextColor + ';">VIEW DETAILS</button>' +
                    '</div>';

                this.bindPopup(popupHTML, {
                    maxWidth: 240,
                    minWidth: 220,
                    className: 'pmp-leaflet-popup',
                    autoPan: true,
                    autoPanPadding: [80, 80],
                    autoPanPaddingTopLeft: [50, 120],
                    autoPanPaddingBottomRight: [50, 50]
                }).openPopup();
            });

            markers.push(marker);
        });

        // Add all markers to cluster group at once
        markerClusterGroup.addLayers(markers);

        // Add cluster group to map
        map.addLayer(markerClusterGroup);
    }

    /**
     * Fallback: Add simple markers without clustering (for when MarkerCluster isn't available)
     */
    function addSimpleMarkersLeaflet(markerColor, accentColor, buttonTextColor) {
        projectsData.forEach(function (project) {
            var marker = L.marker([project.coordinates[1], project.coordinates[0]], {
                icon: L.divIcon({
                    className: 'pmp-leaflet-marker',
                    html: '<div style="background-color: ' + markerColor + '; width: 20px; height: 20px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.4); cursor: pointer;"></div>',
                    iconSize: [20, 20],
                    iconAnchor: [10, 10]
                })
            });

            var projectImage = project.image || pmp_ajax.placeholder_image || '';

            var popupHTML = '<div class="pmp-marker-preview">' +
                '<button class="pmp-marker-popup-close" onclick="pmpCloseMarkerPopup()">✕</button>' +
                '<img class="pmp-marker-project-image" src="' + projectImage + '" alt="' + project.name + '" onerror="this.src=\'' + (pmp_ajax.placeholder_image || '') + '\'">' +
                '<h3>' + project.name + '</h3>' +
                '<p class="pmp-marker-country"><span class="pmp-marker-flag">' + (project.countryFlag || '') + '</span> ' + project.country + '</p>' +
                '<p class="pmp-marker-served">' + parseInt(project.peopleServed).toLocaleString() + ' people served</p>' +
                '<button onclick="pmpOpenProjectPopup(\'' + project.id + '\')" style="background-color: ' + accentColor + '; color: ' + buttonTextColor + ';">VIEW DETAILS</button>' +
                '</div>';

            marker.bindPopup(popupHTML, {
                maxWidth: 240,
                minWidth: 220,
                className: 'pmp-leaflet-popup',
                autoPan: true,
                autoPanPadding: [80, 80]
            });

            marker.addTo(map);
        });
    }

    /**
     * Fit map to project bounds
     */
    function fitMapToBounds() {
        if (!map || projectsData.length === 0) return;

        if (pmp_ajax.use_mapbox) {
            var bounds = new mapboxgl.LngLatBounds();
            projectsData.forEach(function (project) {
                bounds.extend(project.coordinates);
            });
            map.fitBounds(bounds, {
                padding: 100,
                duration: 2000
            });
        } else {
            // Leaflet bounds
            var bounds = L.latLngBounds([]);
            projectsData.forEach(function (project) {
                bounds.extend([project.coordinates[1], project.coordinates[0]]);
            });
            map.fitBounds(bounds, {
                padding: [50, 50],
                maxZoom: 15
            });
        }
    }

    /**
     * Reset map view to default
     */
    function resetMapView() {
        if (!map) return;

        if (pmp_ajax.use_mapbox) {
            map.flyTo({
                center: [0, 20],
                zoom: 2,
                duration: 2000
            });
        } else {
            map.setView([20, 0], 2);
        }
    }

    /**
     * Hide loading screen
     */
    function hideLoadingScreen() {
        var loadingScreen = document.getElementById('pmp-loading');
        if (loadingScreen) {
            loadingScreen.classList.add('hidden');
        }
    }

    /**
     * Setup event listeners
     */
    function setupEventListeners() {
        // Country filter
        var countryFilter = document.getElementById('pmp-country-filter');
        if (countryFilter) {
            countryFilter.addEventListener('change', function () {
                currentFilter.country = this.value;
                closeAllPopups();
                loadProjects();
            });
        }

        // Project type filter
        var projectTypeFilter = document.getElementById('pmp-project-type-filter');
        if (projectTypeFilter) {
            projectTypeFilter.addEventListener('change', function () {
                currentFilter.projectType = parseInt(this.value) || 0;
                closeAllPopups();
                loadProjects();
            });
        }

        // Solution type filter
        var typeFilter = document.getElementById('pmp-type-filter');
        if (typeFilter) {
            typeFilter.addEventListener('change', function () {
                currentFilter.solutionType = parseInt(this.value) || 0;
                closeAllPopups();
                loadProjects();
            });
        }

        // Search toggle
        var searchToggle = document.getElementById('pmp-search-toggle');
        var searchBar = document.getElementById('pmp-search-bar');
        var searchInput = document.getElementById('pmp-search-input');

        if (searchToggle && searchBar) {
            searchToggle.addEventListener('click', function () {
                searchBar.classList.toggle('active');
                if (searchBar.classList.contains('active') && searchInput) {
                    searchInput.focus();
                }
            });
        }

        // Search input
        if (searchInput) {
            var searchTimeout;
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                var value = this.value;
                searchTimeout = setTimeout(function () {
                    currentFilter.search = value;
                    loadProjects();
                }, 300);
            });
        }

        // Search close
        var searchClose = document.getElementById('pmp-search-close');
        if (searchClose && searchInput && searchBar) {
            searchClose.addEventListener('click', function () {
                searchInput.value = '';
                searchBar.classList.remove('active');
                currentFilter.search = '';
                loadProjects();
            });
        }

        // Popup close
        var popupClose = document.getElementById('pmp-popup-close');
        if (popupClose) {
            popupClose.addEventListener('click', closeProjectPopup);
        }

        // Popup overlay click
        var popupOverlay = document.getElementById('pmp-popup-overlay');
        if (popupOverlay) {
            popupOverlay.addEventListener('click', closeProjectPopup);
        }

        // Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeProjectPopup();
                if (searchBar && searchBar.classList.contains('active')) {
                    searchBar.classList.remove('active');
                }
            }
        });
    }

})();
