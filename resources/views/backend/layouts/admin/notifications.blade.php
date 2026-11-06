{{-- @extends('backend.master')

@section('title')
    Manual Notification
@endsection

@section('body')

<div class="page-header">
    <div>
        <h1 class="page-title">Send Manual Notification</h1>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="card">
    <div class="card-body">
        <form action="{{ route('notifications.send') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Message</label>
                <textarea name="message" class="form-control" rows="4" required></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">Select Users</label>
                <div class="border p-2" style="max-height:200px; overflow-y:auto;">
                    @foreach($users as $user)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="users[]" value="{{ $user->id }}">
                            <label class="form-check-label">
                                {{ $user->name }} ({{ $user->email }})
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                Send Notification
            </button>
        </form>
    </div>
</div>

@endsection --}}
