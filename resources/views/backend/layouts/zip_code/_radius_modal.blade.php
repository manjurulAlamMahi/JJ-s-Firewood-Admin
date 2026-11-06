{{-- 
    RADIUS MAP TOOL MODAL
    Include this in your zip code index page:
    @include('backend.layouts.zip_code._radius_modal')
    
    Also add this button wherever you want to trigger it:
    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#radius-modal">
        <i class="fa fa-map-marker-alt me-1"></i> Find by Radius
    </button>
--}}

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #radius-map { height: 500px; width: 100%; border-radius: 8px; }

        .radius-controls {
            display: flex; align-items: center; gap: 12px;
            padding: 16px; background: #f8f9fa; border-radius: 8px;
        }
        .radius-controls input[type="range"] { flex: 1; accent-color: #a52a2a; }
        .radius-controls input[type="number"] { width: 80px; text-align: center; }

        .postcode-results-table { max-height: 320px; overflow-y: auto; }
        .postcode-results-table table { margin-bottom: 0; }
        .postcode-results-table thead th { position: sticky; top: 0; background: #fff; z-index: 1; }

        .badge-existing { background: #6c757d; color: #fff; }
        .badge-available { background: #28a745; color: #fff; }
        .badge-loading { background: #ffc107; color: #333; }

        .radius-stats { display: flex; gap: 16px; flex-wrap: wrap; }
        .radius-stat-card {
            flex: 1; min-width: 120px; padding: 12px 16px;
            background: #fff; border-radius: 8px; border: 1px solid #dee2e6; text-align: center;
        }
        .radius-stat-card .stat-value { font-size: 1.5rem; font-weight: 700; color: #a52a2a; }
        .radius-stat-card .stat-label { font-size: 0.8rem; color: #6c757d; text-transform: uppercase; }

        .map-legend { display: flex; gap: 16px; padding: 8px 0; font-size: 0.85rem; color: #555; }
        .map-legend span::before {
            content: ''; display: inline-block; width: 12px; height: 12px;
            border-radius: 50%; margin-right: 6px; vertical-align: middle;
        }
        .map-legend .legend-available::before { background: #28a745; }
        .map-legend .legend-existing::before { background: #6c757d; }
        .map-legend .legend-centre::before { background: #a52a2a; }

        #radius-modal .modal-dialog { max-width: 1000px; }
        #radius-modal .modal-body { padding: 20px; }
        .postcode-row-existing { opacity: 0.5; background: #f5f5f5; }
        .postcode-row-existing td { text-decoration: line-through; color: #999; }
    </style>
@endpush

<!-- ==================== RADIUS MAP MODAL ==================== -->
<div class="modal fade" id="radius-modal" tabindex="-1" aria-labelledby="radiusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: #a52a2a; color: #fff;">
                <h5 class="modal-title" id="radiusModalLabel">
                    <i class="fa fa-map-marker-alt me-2"></i>Find Postcodes by Radius
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Map Legend -->
                <div class="map-legend mb-2">
                    <span class="legend-centre">Centre Point (drag to move)</span>
                    <span class="legend-available">Available Postcodes</span>
                    <span class="legend-existing">Already Added</span>
                </div>

                <!-- Map -->
                <div id="radius-map" class="mb-3"></div>

                <!-- Radius Controls -->
                <div class="radius-controls mb-3">
                    <label class="fw-bold mb-0" style="white-space: nowrap;">Radius:</label>
                    <input type="range" id="radius-slider" min="1" max="150" value="25" step="1">
                    <input type="number" id="radius-input" class="form-control form-control-sm" min="1" max="150" value="25">
                    <span class="fw-bold" style="white-space: nowrap;">km</span>
                </div>

                <!-- Stats -->
                <div class="radius-stats mb-3">
                    <div class="radius-stat-card">
                        <div class="stat-value" id="stat-total">0</div>
                        <div class="stat-label">In Radius</div>
                    </div>
                    <div class="radius-stat-card">
                        <div class="stat-value" id="stat-available">0</div>
                        <div class="stat-label">Available</div>
                    </div>
                    <div class="radius-stat-card">
                        <div class="stat-value" id="stat-existing">0</div>
                        <div class="stat-label">Already Added</div>
                    </div>
                </div>

                <!-- Postcode Results Table -->
                <div class="postcode-results-table">
                    <table class="table table-sm table-bordered table-hover mb-0" id="radius-results-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all-postcodes" checked></th>
                                <th>Postcode</th>
                                <th>Suburb</th>
                                <th>Distance</th>
                                <th>Drive Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="radius-results-body">
                            <tr><td colspan="6" class="text-center text-muted py-4">Adjust the radius to find postcodes</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <span class="text-muted" id="selection-count">0 postcodes selected</span>
                <div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="btn-add-postcodes" disabled>
                        <i class="fa fa-plus me-1"></i> Create Group with Selected
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    $(document).ready(function() {
        // ============================
        // WA POSTCODE DATASET
        // ============================
        const WA_POSTCODES_RAW = [
            {postcode:"6000",suburb:"Perth",lat:-31.9505,lng:115.8605},
            {postcode:"6003",suburb:"Highgate",lat:-31.9400,lng:115.8730},
            {postcode:"6004",suburb:"East Perth",lat:-31.9560,lng:115.8740},
            {postcode:"6005",suburb:"West Perth",lat:-31.9470,lng:115.8450},
            {postcode:"6006",suburb:"North Perth",lat:-31.9280,lng:115.8600},
            {postcode:"6007",suburb:"Leederville",lat:-31.9350,lng:115.8420},
            {postcode:"6008",suburb:"Subiaco",lat:-31.9490,lng:115.8270},
            {postcode:"6009",suburb:"Crawley",lat:-31.9780,lng:115.8150},
            {postcode:"6010",suburb:"Claremont",lat:-31.9800,lng:115.7830},
            {postcode:"6011",suburb:"Cottesloe",lat:-31.9930,lng:115.7640},
            {postcode:"6012",suburb:"Mosman Park",lat:-32.0010,lng:115.7650},
            {postcode:"6014",suburb:"Wembley",lat:-31.9340,lng:115.8090},
            {postcode:"6015",suburb:"City Beach",lat:-31.9340,lng:115.7580},
            {postcode:"6016",suburb:"Mt Hawthorn",lat:-31.9170,lng:115.8350},
            {postcode:"6017",suburb:"Osborne Park",lat:-31.9010,lng:115.8130},
            {postcode:"6018",suburb:"Yokine",lat:-31.8940,lng:115.8560},
            {postcode:"6019",suburb:"Scarborough",lat:-31.8940,lng:115.7620},
            {postcode:"6020",suburb:"Karrinyup",lat:-31.8730,lng:115.7780},
            {postcode:"6021",suburb:"Balcatta",lat:-31.8730,lng:115.8260},
            {postcode:"6022",suburb:"Hamersley",lat:-31.8560,lng:115.8130},
            {postcode:"6023",suburb:"Duncraig",lat:-31.8350,lng:115.7750},
            {postcode:"6024",suburb:"Warwick",lat:-31.8430,lng:115.8090},
            {postcode:"6025",suburb:"Hillarys",lat:-31.8080,lng:115.7380},
            {postcode:"6026",suburb:"Kingsley",lat:-31.8100,lng:115.7970},
            {postcode:"6027",suburb:"Joondalup",lat:-31.7730,lng:115.7600},
            {postcode:"6028",suburb:"Woodvale",lat:-31.7890,lng:115.7920},
            {postcode:"6029",suburb:"Trigg",lat:-31.8720,lng:115.7540},
            {postcode:"6030",suburb:"Clarkson",lat:-31.6900,lng:115.7260},
            {postcode:"6031",suburb:"Merriwa",lat:-31.6650,lng:115.7110},
            {postcode:"6032",suburb:"Nowergup",lat:-31.6290,lng:115.7400},
            {postcode:"6033",suburb:"Carramar",lat:-31.6830,lng:115.7480},
            {postcode:"6034",suburb:"Eglinton",lat:-31.5880,lng:115.6990},
            {postcode:"6035",suburb:"Yanchep",lat:-31.5470,lng:115.6350},
            {postcode:"6036",suburb:"Two Rocks",lat:-31.5010,lng:115.5890},
            {postcode:"6038",suburb:"Butler",lat:-31.6470,lng:115.7060},
            {postcode:"6041",suburb:"Bullsbrook",lat:-31.6690,lng:116.0460},
            {postcode:"6042",suburb:"Upper Swan",lat:-31.7420,lng:116.0280},
            {postcode:"6044",suburb:"Chittering",lat:-31.4860,lng:116.0680},
            {postcode:"6050",suburb:"Mount Lawley",lat:-31.9340,lng:115.8780},
            {postcode:"6051",suburb:"Maylands",lat:-31.9320,lng:115.8960},
            {postcode:"6052",suburb:"Bedford",lat:-31.9100,lng:115.8990},
            {postcode:"6053",suburb:"Bayswater",lat:-31.9120,lng:115.9220},
            {postcode:"6054",suburb:"Bassendean",lat:-31.9030,lng:115.9480},
            {postcode:"6055",suburb:"Guildford",lat:-31.8990,lng:115.9700},
            {postcode:"6056",suburb:"Midland",lat:-31.8860,lng:116.0130},
            {postcode:"6057",suburb:"Swan View",lat:-31.8830,lng:116.0480},
            {postcode:"6058",suburb:"Forrestfield",lat:-31.9870,lng:116.0080},
            {postcode:"6059",suburb:"Dianella",lat:-31.8910,lng:115.8740},
            {postcode:"6060",suburb:"Nollamara",lat:-31.8790,lng:115.8490},
            {postcode:"6061",suburb:"Mirrabooka",lat:-31.8570,lng:115.8600},
            {postcode:"6062",suburb:"Morley",lat:-31.8840,lng:115.9090},
            {postcode:"6063",suburb:"Beechboro",lat:-31.8580,lng:115.9310},
            {postcode:"6064",suburb:"Alexander Heights",lat:-31.8280,lng:115.8610},
            {postcode:"6065",suburb:"Wanneroo",lat:-31.7500,lng:115.8100},
            {postcode:"6066",suburb:"Ballajura",lat:-31.8370,lng:115.8940},
            {postcode:"6067",suburb:"Cullacabardee",lat:-31.8090,lng:115.8980},
            {postcode:"6068",suburb:"Ellenbrook",lat:-31.7690,lng:115.9690},
            {postcode:"6069",suburb:"The Vines",lat:-31.7590,lng:115.9910},
            {postcode:"6070",suburb:"Darlington",lat:-31.9110,lng:116.0740},
            {postcode:"6071",suburb:"Mundaring",lat:-31.9010,lng:116.1660},
            {postcode:"6072",suburb:"Sawyers Valley",lat:-31.9060,lng:116.2080},
            {postcode:"6073",suburb:"Stoneville",lat:-31.8720,lng:116.1420},
            {postcode:"6076",suburb:"Kalamunda",lat:-31.9750,lng:116.0580},
            {postcode:"6078",suburb:"Pickering Brook",lat:-31.9850,lng:116.1020},
            {postcode:"6079",suburb:"Bickley",lat:-32.0060,lng:116.0690},
            {postcode:"6081",suburb:"Mundijong",lat:-32.2920,lng:115.9840},
            {postcode:"6082",suburb:"Chidlow",lat:-31.8620,lng:116.2680},
            {postcode:"6083",suburb:"Wooroloo",lat:-31.8100,lng:116.3180},
            {postcode:"6084",suburb:"Wundowie",lat:-31.7720,lng:116.3740},
            {postcode:"6090",suburb:"Malaga",lat:-31.8620,lng:115.8930},
            {postcode:"6100",suburb:"Victoria Park",lat:-31.9760,lng:115.8950},
            {postcode:"6101",suburb:"Carlisle",lat:-31.9790,lng:115.9200},
            {postcode:"6102",suburb:"Rivervale",lat:-31.9610,lng:115.9220},
            {postcode:"6103",suburb:"Lathlain",lat:-31.9650,lng:115.9060},
            {postcode:"6104",suburb:"Belmont",lat:-31.9490,lng:115.9310},
            {postcode:"6105",suburb:"Cloverdale",lat:-31.9650,lng:115.9400},
            {postcode:"6106",suburb:"Welshpool",lat:-31.9900,lng:115.9470},
            {postcode:"6107",suburb:"Cannington",lat:-32.0170,lng:115.9340},
            {postcode:"6108",suburb:"Thornlie",lat:-32.0580,lng:115.9530},
            {postcode:"6109",suburb:"Maddington",lat:-32.0410,lng:115.9830},
            {postcode:"6110",suburb:"Gosnells",lat:-32.0810,lng:116.0050},
            {postcode:"6111",suburb:"Kenwick",lat:-32.0290,lng:115.9690},
            {postcode:"6112",suburb:"Armadale",lat:-32.1530,lng:116.0150},
            {postcode:"6113",suburb:"Wungong",lat:-32.1750,lng:116.0050},
            {postcode:"6114",suburb:"Kelmscott",lat:-32.1270,lng:116.0200},
            {postcode:"6115",suburb:"Seville Grove",lat:-32.1510,lng:115.9900},
            {postcode:"6116",suburb:"Champion Lakes",lat:-32.1100,lng:115.9680},
            {postcode:"6119",suburb:"Karragullen",lat:-32.1150,lng:116.1010},
            {postcode:"6120",suburb:"Roleystone",lat:-32.1080,lng:116.0750},
            {postcode:"6121",suburb:"Oakford",lat:-32.3100,lng:115.9010},
            {postcode:"6122",suburb:"Byford",lat:-32.2220,lng:116.0050},
            {postcode:"6123",suburb:"Mundijong",lat:-32.2920,lng:115.9840},
            {postcode:"6124",suburb:"Serpentine",lat:-32.3610,lng:115.9760},
            {postcode:"6125",suburb:"Jarrahdale",lat:-32.3360,lng:116.0560},
            {postcode:"6126",suburb:"Keysbrook",lat:-32.4370,lng:115.9650},
            {postcode:"6146",suburb:"Huntingdale",lat:-32.0700,lng:115.9600},
            {postcode:"6147",suburb:"Langford",lat:-32.0440,lng:115.9450},
            {postcode:"6148",suburb:"Shelley",lat:-32.0350,lng:115.9230},
            {postcode:"6149",suburb:"Riverton",lat:-32.0370,lng:115.9050},
            {postcode:"6150",suburb:"Bull Creek",lat:-32.0510,lng:115.8620},
            {postcode:"6151",suburb:"South Perth",lat:-31.9740,lng:115.8650},
            {postcode:"6152",suburb:"Como",lat:-31.9920,lng:115.8680},
            {postcode:"6153",suburb:"Applecross",lat:-32.0130,lng:115.8340},
            {postcode:"6154",suburb:"Booragoon",lat:-32.0380,lng:115.8340},
            {postcode:"6155",suburb:"Willetton",lat:-32.0530,lng:115.8880},
            {postcode:"6156",suburb:"Melville",lat:-32.0420,lng:115.8150},
            {postcode:"6157",suburb:"Palmyra",lat:-32.0440,lng:115.7870},
            {postcode:"6158",suburb:"East Fremantle",lat:-32.0350,lng:115.7650},
            {postcode:"6159",suburb:"North Fremantle",lat:-32.0380,lng:115.7510},
            {postcode:"6160",suburb:"Fremantle",lat:-32.0569,lng:115.7439},
            {postcode:"6162",suburb:"Beaconsfield",lat:-32.0700,lng:115.7570},
            {postcode:"6163",suburb:"Hamilton Hill",lat:-32.0820,lng:115.7710},
            {postcode:"6164",suburb:"Success",lat:-32.1430,lng:115.8490},
            {postcode:"6165",suburb:"Munster",lat:-32.1360,lng:115.7810},
            {postcode:"6166",suburb:"Kwinana",lat:-32.2370,lng:115.7920},
            {postcode:"6167",suburb:"Wellard",lat:-32.2660,lng:115.8330},
            {postcode:"6168",suburb:"Rockingham",lat:-32.2820,lng:115.7370},
            {postcode:"6169",suburb:"Safety Bay",lat:-32.3090,lng:115.7350},
            {postcode:"6170",suburb:"Warnbro",lat:-32.3310,lng:115.7510},
            {postcode:"6171",suburb:"Baldivis",lat:-32.3280,lng:115.7850},
            {postcode:"6172",suburb:"Port Kennedy",lat:-32.3680,lng:115.7510},
            {postcode:"6173",suburb:"Secret Harbour",lat:-32.3970,lng:115.7330},
            {postcode:"6174",suburb:"Golden Bay",lat:-32.4280,lng:115.7590},
            {postcode:"6175",suburb:"Singleton",lat:-32.4550,lng:115.7450},
            {postcode:"6176",suburb:"Lakelands",lat:-32.4840,lng:115.7570},
            {postcode:"6180",suburb:"Dawesville",lat:-32.6240,lng:115.6410},
            {postcode:"6181",suburb:"Pinjarra",lat:-32.6300,lng:115.8750},
            {postcode:"6182",suburb:"Ravenswood",lat:-32.5940,lng:115.7590},
            {postcode:"6207",suburb:"Dwellingup",lat:-32.7130,lng:116.0640},
            {postcode:"6208",suburb:"North Dandalup",lat:-32.5220,lng:115.9710},
            {postcode:"6209",suburb:"South Yunderup",lat:-32.5780,lng:115.7920},
            {postcode:"6210",suburb:"Mandurah",lat:-32.5270,lng:115.7220},
            {postcode:"6211",suburb:"Falcon",lat:-32.5790,lng:115.6620},
            {postcode:"6212",suburb:"Barragup",lat:-32.5390,lng:115.8090},
            {postcode:"6213",suburb:"Waroona",lat:-32.8430,lng:115.9220},
            {postcode:"6214",suburb:"Yarloop",lat:-32.9560,lng:115.8960},
            {postcode:"6215",suburb:"Harvey",lat:-33.0830,lng:115.8920},
            {postcode:"6218",suburb:"Australind",lat:-33.2750,lng:115.7200},
            {postcode:"6220",suburb:"Bunbury",lat:-33.3270,lng:115.6370},
            {postcode:"6230",suburb:"Bunbury",lat:-33.3310,lng:115.6340},
            {postcode:"6302",suburb:"York",lat:-31.8870,lng:116.7670},
            {postcode:"6304",suburb:"Northam",lat:-31.6540,lng:116.6640},
            {postcode:"6566",suburb:"Toodyay",lat:-31.5510,lng:116.4730},
        ];

        // ============================
        // RADIUS MAP TOOL
        // ============================
        const JJS_CENTRE = { lat: -32.3100, lng: 115.9010 }; // 846 Rowley Road, Oakford 6121
        let map, centreMarker, radiusCircle;
        let existingPostcodes = new Set();
        let currentResults = [];
        let routeCache = {};
        let mapInitialized = false;

        // Fetch existing postcodes from all groups
        function loadExistingPostcodes() {
            return $.ajax({
                url: '{{ route("zip-code.all-existing") }}',
                type: 'GET',
                success: (data) => {
                    existingPostcodes = new Set(data.map(z => z.zip_code));
                }
            });
        }

        // Haversine distance (km)
        function haversine(lat1, lng1, lat2, lng2) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLng/2)**2;
            return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        }

        // OSRM driving route (free, no API key)
        async function getDrivingInfo(fromLat, fromLng, toLat, toLng) {
            const key = `${fromLat},${fromLng}-${toLat},${toLng}`;
            if (routeCache[key]) return routeCache[key];
            try {
                const url = `https://router.project-osrm.org/route/v1/driving/${fromLng},${fromLat};${toLng},${toLat}?overview=false`;
                const resp = await fetch(url);
                const data = await resp.json();
                if (data.code === 'Ok' && data.routes.length > 0) {
                    const route = data.routes[0];
                    const result = { distanceKm: (route.distance / 1000).toFixed(1), durationMin: Math.round(route.duration / 60) };
                    routeCache[key] = result;
                    return result;
                }
            } catch (e) { console.warn('OSRM request failed:', e); }
            return null;
        }

        // Find postcodes in radius and build table
        async function updateResults() {
            const centre = centreMarker.getLatLng();
            const radiusKm = parseFloat($('#radius-slider').val());

            let matches = WA_POSTCODES_RAW
                .map(p => ({ ...p, straightKm: haversine(centre.lat, centre.lng, p.lat, p.lng), isExisting: existingPostcodes.has(p.postcode) }))
                .filter(p => p.straightKm <= radiusKm)
                .sort((a, b) => a.straightKm - b.straightKm);

            const available = matches.filter(m => !m.isExisting);
            $('#stat-total').text(matches.length);
            $('#stat-available').text(available.length);
            $('#stat-existing').text(matches.length - available.length);

            let html = '';
            if (matches.length === 0) {
                html = '<tr><td colspan="6" class="text-center text-muted py-4">No postcodes found in this radius</td></tr>';
            } else {
                matches.forEach((m, i) => {
                    const rowClass = m.isExisting ? 'postcode-row-existing' : '';
                    const checkbox = m.isExisting
                        ? '<input type="checkbox" disabled>'
                        : `<input type="checkbox" class="postcode-checkbox" data-postcode="${m.postcode}" checked>`;
                    const statusBadge = m.isExisting
                        ? '<span class="badge badge-existing">Already Added</span>'
                        : '<span class="badge badge-available">Available</span>';
                    html += `<tr class="${rowClass}" data-index="${i}">
                        <td>${checkbox}</td>
                        <td><strong>${m.postcode}</strong></td>
                        <td>${m.suburb}</td>
                        <td class="td-distance">${m.straightKm.toFixed(1)} km</td>
                        <td class="td-drive"><span class="badge badge-loading">Calculating...</span></td>
                        <td>${statusBadge}</td>
                    </tr>`;
                });
            }

            $('#radius-results-body').html(html);
            currentResults = matches;
            updateSelectionCount();

            // Fetch OSRM driving info in batches
            for (let i = 0; i < matches.length; i++) {
                const m = matches[i];
                const info = await getDrivingInfo(centre.lat, centre.lng, m.lat, m.lng);
                const row = $(`#radius-results-body tr[data-index="${i}"]`);
                if (info) {
                    row.find('.td-distance').text(info.distanceKm + ' km');
                    row.find('.td-drive').html(info.durationMin + ' min');
                    matches[i].driveKm = info.distanceKm;
                    matches[i].driveMin = info.durationMin;
                } else {
                    row.find('.td-drive').html('<span class="text-muted">~' + Math.round(m.straightKm / 60 * 60) + ' min</span>');
                }
                if (i < matches.length - 1) await new Promise(r => setTimeout(r, 80));
            }
        }

        // Update map markers
        let postcodeMarkers = [];
        function updateMapMarkers() {
            postcodeMarkers.forEach(m => map.removeLayer(m));
            postcodeMarkers = [];
            const centre = centreMarker.getLatLng();
            const radiusKm = parseFloat($('#radius-slider').val());

            WA_POSTCODES_RAW.forEach(p => {
                const dist = haversine(centre.lat, centre.lng, p.lat, p.lng);
                if (dist > radiusKm) return;
                const isExisting = existingPostcodes.has(p.postcode);
                const color = isExisting ? '#6c757d' : '#28a745';
                const marker = L.circleMarker([p.lat, p.lng], {
                    radius: 6, fillColor: color, color: '#fff', weight: 1.5, opacity: 1, fillOpacity: isExisting ? 0.4 : 0.85
                }).bindTooltip(`${p.postcode} - ${p.suburb}`, { direction: 'top', offset: [0, -8] });
                marker.addTo(map);
                postcodeMarkers.push(marker);
            });
        }

        function updateSelectionCount() {
            const checked = $('.postcode-checkbox:checked').length;
            $('#selection-count').text(checked + ' postcodes selected');
            $('#btn-add-postcodes').prop('disabled', checked === 0);
        }

        // Debounced update
        let updateTimer;
        function debouncedUpdate() {
            clearTimeout(updateTimer);
            updateTimer = setTimeout(() => {
                const radiusKm = parseFloat($('#radius-slider').val());
                const centre = centreMarker.getLatLng();
                radiusCircle.setLatLng(centre);
                radiusCircle.setRadius(radiusKm * 1000);
                updateMapMarkers();
                updateResults();
            }, 300);
        }

        // Init map when modal opens
        $('#radius-modal').on('shown.bs.modal', function() {
            if (!mapInitialized) {
                map = L.map('radius-map').setView([JJS_CENTRE.lat, JJS_CENTRE.lng], 10);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors', maxZoom: 18
                }).addTo(map);

                centreMarker = L.marker([JJS_CENTRE.lat, JJS_CENTRE.lng], {
                    draggable: true,
                    icon: L.divIcon({
                        className: '',
                        html: '<div style="background:#a52a2a;width:16px;height:16px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.4);"></div>',
                        iconSize: [16, 16], iconAnchor: [8, 8]
                    })
                }).bindTooltip('JJs Firewood - Drag to move', { direction: 'top', offset: [0, -12] }).addTo(map);
                centreMarker.on('dragend', debouncedUpdate);

                radiusCircle = L.circle([JJS_CENTRE.lat, JJS_CENTRE.lng], {
                    radius: 25000, color: '#a52a2a', fillColor: '#a52a2a',
                    fillOpacity: 0.08, weight: 2, dashArray: '8, 6'
                }).addTo(map);
                mapInitialized = true;
            } else {
                map.invalidateSize();
            }
            loadExistingPostcodes().then(() => debouncedUpdate());
        });

        // Slider / input sync
        $('#radius-slider').on('input', function() { $('#radius-input').val(this.value); debouncedUpdate(); });
        $('#radius-input').on('change', function() {
            let val = Math.max(1, Math.min(150, parseInt(this.value) || 25));
            this.value = val; $('#radius-slider').val(val); debouncedUpdate();
        });

        // Select all
        $(document).on('change', '#select-all-postcodes', function() {
            $('.postcode-checkbox').prop('checked', this.checked); updateSelectionCount();
        });
        $(document).on('change', '.postcode-checkbox', updateSelectionCount);

        // Create Group with selected postcodes
        $('#btn-add-postcodes').on('click', function() {
            const selected = [];
            const details = [];
            $('.postcode-checkbox:checked').each(function() {
                const postcode = $(this).data('postcode').toString();
                selected.push(postcode);
                const row = $(this).closest('tr');
                details.push({
                    postcode,
                    suburb: row.find('td:eq(2)').text(),
                    distance: row.find('.td-distance').text(),
                    drive: row.find('.td-drive').text()
                });
            });
            if (selected.length === 0) { toastr.warning('No postcodes selected.'); return; }

            // Store details for the create-group page to pick up
            sessionStorage.setItem('radius_postcodes', JSON.stringify(selected));
            sessionStorage.setItem('radius_postcodes_details', JSON.stringify(details));

            // Redirect to create group page with postcodes in URL
            window.location.href = '{{ route("zip-code.create-group") }}?from_radius=1&postcodes=' + selected.join(',');
        });
    });
    </script>
@endpush
