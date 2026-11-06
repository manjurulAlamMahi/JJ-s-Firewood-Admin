@extends('backend.master')

@section('title', 'Admin Users')

@section('body')
    <div class="page-header">
        <div>
            <h1 class="page-title">Admin Users</h1>
            <p class="text-muted mb-0">People who can log in to this admin panel. Customers aren't listed here.</p>
        </div>
        <div class="ms-auto pageheader-btn">
            <a href="{{ route('users.create') }}" class="btn btn-danger">Add Admin</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Two-factor</th>
                            <th>Added</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($admins as $admin)
                            <tr>
                                <td>
                                    {{ $admin->name }}
                                    @if($admin->id === auth()->id())
                                        <span class="badge bg-secondary ms-1">You</span>
                                    @endif
                                </td>
                                <td>{{ $admin->email }}</td>
                                <td>
                                    @if(!empty($admin->two_factor_confirmed_at))
                                        <span class="badge bg-success">On</span>
                                    @else
                                        <span class="badge bg-light text-muted">Off</span>
                                    @endif
                                </td>
                                <td>{{ optional($admin->created_at)->timezone('Australia/Perth')->format('j M Y') }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('users.edit', $admin->id) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    @if($admin->id !== auth()->id())
                                        <form action="{{ route('users.destroy', $admin->id) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Remove admin access for {{ $admin->email }}? Their account is kept, they just can\'t log in here.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Remove access</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No admins found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <p class="text-muted small mt-2">
        Each person should have their own login. Two-factor can be switched on by each admin under My Profile.
    </p>
@endsection
