<!-- Entry Management Section -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fe fe-gift mr-2"></i> Giveaway Entry Management
        </h5>
    </div>
    <div class="card-body">
        <!-- Summary Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6 class="mb-0">Active Entries</h6>
                        <h2 class="mb-0">{{ $activeEntries ?? ($permanentEntries + $activePurchaseEntries) }}</h2>
                        <small>Currently in the draw</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h6 class="mb-0">Permanent Entries</h6>
                        <h2 class="mb-0">{{ $permanentEntries ?? $rewardEntries }}</h2>
                        <small>From $200+ orders & admin gifts</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6 class="mb-0">Active Purchases</h6>
                        <h2 class="mb-0">{{ $activePurchaseEntries ?? 0 }}</h2>
                        <small>Paid entries, not yet drawn</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <h6 class="mb-0">Used Entries</h6>
                        <h2 class="mb-0">{{ $usedPurchaseEntries ?? 0 }}</h2>
                        <small>Drawn / past giveaways</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Remove Entry Form -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0">Add or Remove Reward Entries</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fe fe-info mr-2"></i>
                    <strong>Note:</strong> Reward entries can be used in any active giveaway. They are not tied to a specific giveaway.
                </div>

                <form action="{{ route('customer-management.manage-entries', $customer->id) }}" method="POST">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Action <span class="text-danger">*</span></label>
                                <select name="action" class="form-control" required>
                                    <option value="add">Add Entries</option>
                                    <option value="remove">Remove Entries</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Number of Entries <span class="text-danger">*</span></label>
                                <input type="number" name="quantity" class="form-control" min="1" max="1000" required placeholder="e.g., 10">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fe fe-check mr-2"></i> Update Entries
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <label>Reason (Optional)</label>
                        <input type="text" name="reason" class="form-control" placeholder="e.g., Customer service gesture, Compensation for issue">
                        <small class="form-text text-muted">This will be recorded in the entry history</small>
                    </div>
                </form>
            </div>
        </div>

        <!-- Entry Breakdown Table -->
        <div class="card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Entry Records ({{ $entries->count() }} records)</h6>
                <small class="text-muted">Showing individual entry transactions</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th width="25%">Giveaway</th>
                                <th width="12%">Entries</th>
                                <th width="18%">Type</th>
                                <th width="15%">Status</th>
                                <th width="12%">Paid</th>
                                <th width="18%">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($entries->take(10) as $entry)
                                <tr>
                                    <td>
                                        @if($entry->giveaway_id)
                                            <small>
                                                <a href="{{ route('giveaways.show', $entry->giveaway_id) }}" target="_blank" class="text-primary">
                                                    {{ $entry->giveaway->prize_name ?? 'Giveaway #' . $entry->giveaway_id }}
                                                </a>
                                            </small>
                                        @else
                                            <span class="badge badge-success badge-sm">Global Rewards</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong class="text-primary">{{ number_format($entry->entry_count) }}</strong>
                                    </td>
                                    <td>
                                        @if($entry->purchase_type === 'reward')
                                            <span class="badge badge-warning badge-sm" title="Always counts toward every giveaway">Permanent Entry</span>
                                        @elseif($entry->purchase_type === 'paid')
                                            <span class="badge badge-primary badge-sm">Purchased</span>
                                        @elseif($entry->purchase_type === 'free')
                                            <span class="badge badge-info badge-sm">Free</span>
                                        @elseif($entry->purchase_type === 'firewood')
                                            <span class="badge badge-success badge-sm">Firewood</span>
                                        @elseif($entry->purchase_type === 'discount')
                                            <span class="badge badge-info badge-sm">Discount</span>
                                        @else
                                            <span class="badge badge-secondary badge-sm">{{ ucfirst($entry->purchase_type) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{-- Permanent entries are always active. Other types: drawn=true means used. --}}
                                        @if($entry->purchase_type === 'reward')
                                            <span class="badge badge-success badge-sm">
                                                <i class="fe fe-check-circle"></i> Active
                                            </span>
                                        @elseif($entry->drawn)
                                            <span class="badge badge-secondary badge-sm" title="Used in a past giveaway">
                                                <i class="fe fe-archive"></i> Used
                                            </span>
                                        @else
                                            <span class="badge badge-success badge-sm">
                                                <i class="fe fe-check-circle"></i> Active
                                            </span>
                                        @endif
                                    </td>
                                    <td><small>${{ number_format($entry->amount_paid, 2) }}</small></td>
                                    <td><small>{{ $entry->created_at->format('M d, Y') }}</small></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">
                                        <small>No entries found</small>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($entries->count() > 10)
                    <div class="card-footer text-center bg-light">
                        <small class="text-muted">Showing 10 of {{ $entries->count() }} entry records</small>
                    </div>
                @endif
            </div>
        </div>

        <!-- Entry History -->
        @if($entryHistory->count() > 0)
        <div class="card mt-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Recent Entry Changes (Admin Actions)</h6>
                <small class="text-muted">Last {{ min(20, $entryHistory->count()) }} actions</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th width="20%">Date</th>
                                <th width="15%">Action</th>
                                <th width="15%">Quantity</th>
                                <th width="20%">Admin</th>
                                <th width="30%">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($entryHistory->take(20) as $history)
                                <tr>
                                    <td><small>{{ $history->created_at->format('M d, Y H:i') }}</small></td>
                                    <td>
                                        @if($history->action === 'add')
                                            <span class="badge badge-success badge-sm">Added</span>
                                        @else
                                            <span class="badge badge-danger badge-sm">Removed</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong class="{{ $history->action === 'add' ? 'text-success' : 'text-danger' }}">
                                            {{ $history->action === 'add' ? '+' : '-' }}{{ $history->quantity }}
                                        </strong>
                                    </td>
                                    <td><small>{{ $history->admin->name ?? 'System' }}</small></td>
                                    <td><small class="text-muted">{{ $history->reason ?? '-' }}</small></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<style>
    /* CRITICAL: Fix all text visibility */
    .card, .card-body, .card-header, .card-footer,
    .table, .table td, .table th, 
    .table tbody tr td, .table thead tr th,
    .form-control, .form-label, label,
    .alert, .badge, small, .text-muted {
        color: #1a1a1a !important;
    }
    
    /* Keep white text only in colored card headers */
    .card.bg-primary *, .card.bg-success *, .card.bg-info * {
        color: #ffffff !important;
    }
    
    /* Badge colors */
    .badge-success { background-color: #28a745 !important; color: #fff !important; }
    .badge-warning { background-color: #ffc107 !important; color: #000 !important; }
    .badge-info { background-color: #17a2b8 !important; color: #fff !important; }
    .badge-secondary { background-color: #6c757d !important; color: #fff !important; }
    .badge-primary { background-color: #007bff !important; color: #fff !important; }
    .badge-danger { background-color: #dc3545 !important; color: #fff !important; }
    
    /* Table styling */
    .table-sm td, .table-sm th {
        padding: 0.5rem !important;
    }
    .thead-light th {
        background-color: #f8f9fa !important;
        color: #1a1a1a !important;
        font-weight: 600 !important;
    }
    
    /* Links */
    .text-primary, a.text-primary {
        color: #007bff !important;
    }
    
    /* Alert boxes */
    .alert-info {
        background-color: #d1ecf1 !important;
        border-color: #bee5eb !important;
        color: #0c5460 !important;
    }
</style>