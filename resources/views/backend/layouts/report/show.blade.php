@extends('backend.master')

@section('title') Report Details @endsection

@section('body')
<div class="page-header">
    <h1>Report Details</h1>
</div>

<div class="card">
    <div class="card-header">
        <a href="{{ route('report.index') }}" class="btn btn-secondary"> <i class="fa fa-arrow-left"></i> Back to List</a>
    </div>
    <div class="card-body">
        <table class="table table-bordered">
            <tbody>
                <tr>
                    <th style="width: 200px;">User</th>
                    <td>
                        {{ $report->user->name ?? 'N/A' }} <br>
                        <small>{{ $report->user->email ?? 'N/A' }}</small>
                    </td>
                </tr>
                <tr>
                    <th>Message</th>
                    <td>{{ $report->message }}</td>
                </tr>
                <tr>
                    <th>Images</th>
                    <td>
                        @if($report->images->count() > 0)
                            <div class="row">
                                @foreach($report->images as $image)
                                    <div class="col-md-3 mb-2">
                                        <a href="{{ asset($image->image) }}" target="_blank">
                                            <img src="{{ asset($image->image) }}" alt="Report Image" style="width: 100%; height: 150px; object-fit: cover;" class="img-thumbnail">
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            No Images Attached
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Created At</th>
                    <td>{{ $report->created_at->format('d M, Y h:i A') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
