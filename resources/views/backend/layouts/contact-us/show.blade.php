@extends('backend.master')
@section('title')
    Contact Us - View
@endsection
@section('body')
    <!-- PAGE-HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">View Contact Us Entry</h1>
        </div>
        <div class="ms-auto pageheader-btn"></div>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('contact-us.index') }}">Contact Us</a></li>
            <li class="breadcrumb-item active" aria-current="page">View</li>
        </ol>
    </div>
    <!-- PAGE-HEADER END -->

    <div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">View Contact Us Entry</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Name -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Name</label>
                        <div>{{ $contactUs->name }}</div>
                    </div>

                    <!-- Email -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Email</label>
                        <div>{{ $contactUs->email }}</div>
                    </div>

                    <!-- Subject -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Subject</label>
                        <div>{{ $contactUs->subject }}</div>
                    </div>

                    <!-- Attachment -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Attachment</label>
                        <div>
                            @if($contactUs->attachment)
                                <a href="{{ asset($contactUs->attachment) }}" target="_blank" class="btn btn-sm btn-info">View Attachment</a>
                            @else
                                <span class="text-muted">No Attachment</span>
                            @endif
                        </div>
                    </div>

                    <!-- Message -->
                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold">Message</label>
                        <div class="border p-3 rounded" style="background: #f8f9fa;">
                            {!! $contactUs->message !!}
                        </div>
                    </div>
                </div>
                
                <a href="{{ route('contact-us.index') }}" class="btn btn-primary mt-3">Back to List</a>
            </div>
        </div>
    </div>
@endsection
