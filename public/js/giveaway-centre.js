$(document).ready(function() {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    
    console.log('Giveaway Centre loaded');
    
    let giveawaysData = [];
    
    // Load giveaways initially
    loadGiveaways();
    
    window.loadGiveaways = function() {
        $.ajax({
            url: '/giveaway-centre/giveaways/data',
            type: 'GET',
            success: function(response) {
                console.log('Loaded:', response);
                giveawaysData = response.data;
                renderGiveaways();
            },
            error: function(xhr) {
                console.error('Error:', xhr);
                alert('Failed to load giveaways: ' + xhr.statusText);
            }
        });
    };
    
    function renderGiveaways() {
        let tbody = $('#giveawaysTableBody');
        tbody.empty();
        
        if (!giveawaysData || giveawaysData.length === 0) {
            tbody.html('<tr><td colspan="10" class="text-center py-4"><p>No giveaways found</p><a href="/giveaways/create" class="btn btn-success mt-2">Create Your First Giveaway</a></td></tr>');
            return;
        }
        
        giveawaysData.forEach(function(g) {
            let img = g.image ? '/' + g.image : '/no_image/no_image.jpg';
            let row = '<tr data-id="' + g.id + '">' +
                '<td><input type="checkbox" class="giveaway-checkbox" value="' + g.id + '"></td>' +
                '<td><i class="fe fe-menu"></i></td>' +
                '<td><img src="' + img + '" style="width:50px;height:50px;object-fit:cover;border-radius:6px;"></td>' +
                '<td><strong>' + g.prize_name + '</strong></td>' +
                '<td>' + (g.prize_type || '-') + '</td>' +
                '<td>$' + g.prize_value + '</td>' +
                '<td>' + g.draw_date + '</td>' +
                '<td><span class="badge badge-info">' + g.total_entries + ' entries</span></td>' +
                '<td><span class="badge badge-' + g.status_badge + '">' + g.status + '</span></td>' +
                '<td><a href="/giveaways/' + g.id + '/edit" class="btn btn-sm btn-warning"><i class="fe fe-edit"></i></a></td>' +
                '</tr>';
            tbody.append(row);
        });
    }
    
    // Entry Packages Tab
    $('#entry-packages-tab').on('shown.bs.tab', function() {
        loadEntryPackages();
    });
    
    window.loadEntryPackages = function() {
        $.get('/giveaway-centre/entry-packages/data', function(response) {
            let tbody = $('#packagesTableBody');
            tbody.empty();
            
            if (!response.data || response.data.length === 0) {
                tbody.html('<tr><td colspan="9" class="text-center py-4"><p>No packages found</p><a href="/entry-package/create" class="btn btn-success mt-2">Create Your First Package</a></td></tr>');
                return;
            }
            
            response.data.forEach(function(p) {
                let row = '<tr data-id="' + p.id + '">' +
                    '<td><input type="checkbox" value="' + p.id + '"></td>' +
                    '<td><i class="fe fe-menu"></i></td>' +
                    '<td><strong>' + p.name + '</strong></td>' +
                    '<td>' + (p.description || '-') + '</td>' +
                    '<td><span class="badge badge-info">' + p.entry_quantity + '</span></td>' +
                    '<td>$' + p.price + '</td>' +
                    '<td>' + (p.is_featured ? '⭐' : '-') + '</td>' +
                    '<td><span class="badge badge-' + (p.status ? 'success' : 'secondary') + '">' + (p.status ? 'Active' : 'Inactive') + '</span></td>' +
                    '<td><a href="/entry-package/' + p.id + '/edit" class="btn btn-sm btn-warning"><i class="fe fe-edit"></i></a></td>' +
                    '</tr>';
                tbody.append(row);
            });
        });
    };
    
    // Permanent Entries Tab
    $('#permanent-entries-tab').on('shown.bs.tab', function() {
        loadPermanentEntries();
    });
    
    window.loadPermanentEntries = function() {
        $.get('/giveaway-centre/permanent-entries/data', function(response) {
            let tbody = $('#permanentEntriesTableBody');
            tbody.empty();
            
            if (!response.data || response.data.length === 0) {
                tbody.html('<tr><td colspan="8" class="text-center py-4">No users with permanent entries yet</td></tr>');
                return;
            }
            
            response.data.forEach(function(e) {
                let row = '<tr>' +
                    '<td>' + e.user_id + '</td>' +
                    '<td><strong>' + e.user_name + '</strong></td>' +
                    '<td>' + e.user_email + '</td>' +
                    '<td><span class="badge badge-primary" style="font-size:14px;">' + e.permanent_entries + '</span></td>' +
                    '<td><span class="badge badge-success">' + e.total_earned + '</span></td>' +
                    '<td><span class="badge badge-warning" style="color:#212529;">' + e.total_used + '</span></td>' +
                    '<td><small>' + e.last_updated_at + '</small></td>' +
                    '<td><button class="btn btn-sm btn-primary" onclick="alert(\'Edit feature: Add entries modal will open here\')"><i class="fe fe-edit"></i></button></td>' +
                    '</tr>';
                tbody.append(row);
            });
        });
    };
    
    // Search button
    $(document).on('click', 'button:contains("Search")', function() {
        loadGiveaways();
    });
});