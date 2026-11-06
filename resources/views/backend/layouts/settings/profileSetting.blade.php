@extends('backend.master')
@section('title')
    Profile Settings
@endsection
@section('body')
    <x-admin.page-header title="Profile Settings"
        :crumbs="['Settings' => null, 'Profile Settings' => null]" />

<!-- PAGE -->
    <div class="page">
        <div class="page-main">
            <!-- ROW-1 OPEN -->
            <div class="row" id="user-profile">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-lg-12 col-md-12 col-xl-6">
                                    <div class="d-flex flex-wrap align-items-center">
                                        {{-- Profile Image --}}
                                        <div class="profile-img-main position-relative d-inline-block">
                                            <img src="{{ asset(Auth::user()->profile_photo_path) }}" alt="Profile Image"
                                                class="rounded-circle"
                                                style="width: 100px; height: 100px; object-fit: cover; cursor: pointer;"
                                                onclick="document.getElementById('photo-input').click()">

                                            <div class="edit-icon position-absolute bg-danger rounded-circle d-flex align-items-center justify-content-center"
                                                style="bottom: 5px; right: 5px; width: 30px; height: 30px; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.2); opacity: 0; transition: opacity 0.3s ease;"
                                                onclick="document.getElementById('photo-input').click()">
                                                <i class="fas fa-edit text-white" style="font-size: 12px;"></i>
                                            </div>


                                            <form action="{{ route('profile.avatar.update') }}" method="POST"
                                                enctype="multipart/form-data" id="avatar-form">
                                                @csrf
                                                <input type="file" name="photo" id="photo-input" accept="image/*"
                                                    class="d-none"
                                                    onchange="document.getElementById('avatar-form').submit()">
                                            </form>


                                            @if (Auth::user()->profile_photo)
                                                <div class="remove-icon position-absolute bg-danger rounded-circle d-flex align-items-center justify-content-center"
                                                    style="top: 5px; right: 5px; width: 25px; height: 25px; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.2); opacity: 0; transition: opacity 0.3s ease;"
                                                    onclick="removeAvatar()">
                                                    <i class="fas fa-times text-white" style="font-size: 10px;"></i>
                                                </div>

                                                <form action="{{ route('profile.avatar.remove') }}" method="POST"
                                                    id="remove-avatar-form" class="d-none">
                                                    @csrf
                                                </form>
                                            @endif
                                        </div>

                                        {{-- User Info --}}
                                        <div class="ms-4">
                                            <h4>{{ Auth::user()->name }}</h4>
                                            <p class="text-muted mb-2">Member Since:
                                                {{ Auth::user()->created_at->format('M d, Y') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Flash messages --}}
                            @if (session('success'))
                                <div class="alert alert-success mt-3">{{ session('success') }}</div>
                            @endif
                            @if (session('error'))
                                <div class="alert alert-danger mt-3">{{ session('error') }}</div>
                            @endif
                        </div>


                        <div class="border-top">
                            <div class="wideget-user-tab">
                                <div class="tab-menu-heading">
                                    <div class="tabs-menu1">
                                        <ul class="nav nav-tabs custom-tabs border-bottom-0 w-100" role="tablist">
                                            <li class="nav-item flex-grow-1 text-center">
                                                <a href="#profileMain" class="nav-link active" data-bs-toggle="tab" role="tab">Profile</a>
                                            </li>
                                            <li class="nav-item flex-grow-1 text-center">
                                                <a href="#editProfile" class="nav-link" data-bs-toggle="tab" role="tab">Edit Profile</a>
                                            </li>
                                            <li class="nav-item flex-grow-1 text-center">
                                                <a href="#changePassword" class="nav-link" data-bs-toggle="tab" role="tab">Change Password</a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane active show" id="profileMain">
                            <div class="card">
                                <div class="card-body p-0">

                                    <div class="border-top"></div>
                                    <div class="table-responsive p-3">
                                        <h3 class="card-title">Personal Info</h3>
                                        <table class="table row table-borderless">
                                            <tbody class="col-lg-12 col-xl-6 p-0">
                                                <tr>
                                                    <td><strong>Name :</strong> {{ Auth::user()->name }}</td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Email :</strong> {{ Auth::user()->email }}</td>
                                                </tr>
                                            </tbody>

                                        </table>
                                    </div>
                                    <div class="border-top"></div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane" id="editProfile">
                            <div class="card">
                                <div class="card-body border-0">
                                    <form class="form-horizontal" action="{{ route('profile.update') }}" method="POST">
                                        @csrf

                                        @if (session('success') && session('type') == 'profile')
                                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                                {{ session('success') }}
                                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                            </div>
                                        @endif

                                        @if (session('error') && session('type') == 'profile')
                                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                {{ session('error') }}
                                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                            </div>
                                        @endif

                                        <div class="form-group">
                                            <label for="name" class="form-label">Name</label>
                                            <input type="text" name="name" id="name"
                                                value="{{ old('name', Auth::user()->name) }}" class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" name="email" id="email"
                                                value="{{ old('email', Auth::user()->email) }}" class="form-control">
                                        </div>

                                        <div class="text-end">
                                            <button type="submit" class="btn btn-danger">Update</button>
                                        </div>
                                    </form>

                                </div>
                            </div>
                        </div>
                        <div class="tab-pane" id="changePassword">
                            <div class="card">
                                <div class="card-body border-0">
                                    <form class="form-horizontal" action="{{ route('profile.password.update') }}"
                                        method="POST">
                                        @csrf

                                        @if (session('success') && session('type') == 'password')
                                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                                {{ session('success') }}
                                                <button type="button" class="btn-close"
                                                    data-bs-dismiss="alert"></button>
                                            </div>
                                        @endif

                                        @if (session('error') && session('type') == 'password')
                                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                {{ session('error') }}
                                                <button type="button" class="btn-close"
                                                    data-bs-dismiss="alert"></button>
                                            </div>
                                        @endif

                                        <div class="form-group">
                                            <label for="old_password" class="form-label">Current Password</label>
                                            <input type="password" name="old_password" id="old_password"
                                                class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label for="password" class="form-label">New Password</label>
                                            <input type="password" name="password" id="password" class="form-control">
                                        </div>

                                        <div class="form-group">
                                            <label for="password_confirmation" class="form-label">Confirm Password</label>
                                            <input type="password" name="password_confirmation"
                                                id="password_confirmation" class="form-control">
                                        </div>

                                        <div class="text-end">
                                            <button type="submit" class="btn btn-danger">Update</button>
                                        </div>
                                    </form>

                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>


        </div>
    </div>
    </div>
    <!-- CONTAINER CLOSED -->
    </div>
    </div>
    <!-- page -->
@endsection

@push('scripts')
    <script>
        function removeAvatar() {
            if (confirm('Are you sure you want to remove your avatar?')) {
                document.getElementById('remove-avatar-form').submit();
            }
        }

        // Optional: Preview image before upload
        document.getElementById('photo-input').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.profile-img-main img').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>

    <style>
        /* Hide edit icon by default */
        .profile-img-main .edit-icon {
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        /* Show edit icon only when hovering over the profile image container */
        .profile-img-main:hover .edit-icon {
            opacity: 1;
            visibility: visible;
        }

        /* Optional: Add slight dark overlay on hover to make icon more visible */
        .profile-img-main:hover img {
            filter: brightness(0.8);
            transition: filter 0.3s ease;
        }

        /* Scale effect on icon hover */
        .edit-icon:hover {
            transform: scale(1.15);
            transition: transform 0.2s ease;
        }

        /* Keep remove icon always visible if it exists */
        .profile-img-main .remove-icon {
            opacity: 1;
            visibility: visible;
        }
    </style>
@endpush
