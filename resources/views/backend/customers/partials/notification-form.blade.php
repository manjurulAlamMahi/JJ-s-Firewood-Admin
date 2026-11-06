<!-- Send Notification Section -->
<div class="card mt-4">
    <div class="card-header bg-light">
        <h6 class="mb-0">
            <i class="fe fe-bell mr-2"></i> Send Notification to {{ $customer->name }}
        </h6>
    </div>
    <div class="card-body">
        <form action="{{ route('customer-management.send-notification', $customer->id) }}" method="POST">
            @csrf
            
            <div class="row">
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Title <span class="text-danger title-required-star">*</span></label>
                                <input type="text" class="form-control form-control-sm" name="title" id="notif-title" required maxlength="100" placeholder="Notification title">
                                <small class="form-text text-muted" id="title-sms-hint" style="display:none;">Optional for SMS</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Send Via <span class="text-danger">*</span></label>
                                <select class="form-control form-control-sm" name="channel" id="notif-channel" required>
                                    <option value="">Select Channel</option>
                                    @if($customer->fcm_token)
                                        <option value="push">Push Notification</option>
                                    @endif
                                    @if($customer->email)
                                        <option value="email">Email</option>
                                    @endif
                                    @if($customer->phone)
                                        <option value="sms">SMS</option>
                                    @endif
                                    @if($customer->fcm_token && $customer->email && $customer->phone)
                                        <option value="all">All Channels (Push + Email + SMS)</option>
                                    @endif
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Message <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" name="message" rows="3" required maxlength="500" placeholder="Your message..."></textarea>
                        <small class="form-text text-muted">Max 500 characters</small>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body p-3">
                            <h6 class="mb-2"><small>Customer Channels</small></h6>
                            <div class="mb-1">
                                @if($customer->fcm_token)
                                    <span class="badge badge-success badge-sm">Push Ready</span>
                                @else
                                    <span class="badge badge-secondary badge-sm">No FCM</span>
                                @endif
                            </div>
                            <div class="mb-1">
                                @if($customer->email)
                                    <span class="badge badge-success badge-sm">Email: {{ $customer->email }}</span>
                                @else
                                    <span class="badge badge-secondary badge-sm">No Email</span>
                                @endif
                            </div>
                            <div>
                                @if($customer->phone)
                                    <span class="badge badge-success badge-sm">Phone: {{ $customer->phone }}</span>
                                @else
                                    <span class="badge badge-secondary badge-sm">No Phone</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-group mb-0">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fe fe-send mr-1"></i> Send Notification
                </button>
                <button type="reset" class="btn btn-secondary btn-sm">
                    <i class="fe fe-x mr-1"></i> Clear
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('notif-channel').addEventListener('change', function() {
    var isSms = this.value === 'sms';
    var titleInput = document.getElementById('notif-title');
    var hint = document.getElementById('title-sms-hint');
    var star = document.querySelector('.title-required-star');

    if (isSms) {
        titleInput.removeAttribute('required');
        hint.style.display = 'block';
        star.style.display = 'none';
    } else {
        titleInput.setAttribute('required', 'required');
        hint.style.display = 'none';
        star.style.display = 'inline';
    }
});
</script>