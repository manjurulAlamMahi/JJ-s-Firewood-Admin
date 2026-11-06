@extends('backend.master')

@section('title') Review Details @endsection

@section('body')
<div class="page-header">
    <h1>Review Details</h1>
</div>

<div class="card">
    <div class="card-header">
        <a href="{{ route('review.index') }}" class="btn btn-secondary"> <i class="fa fa-arrow-left"></i> Back to List</a>
    </div>
    <div class="card-body">
        <table class="table table-bordered">
            <tbody>
                <tr>
                    <th style="width: 200px;">User</th>
                    <td>
                        {{ $review->user->name ?? 'N/A' }} <br>
                        <small>{{ $review->user->email ?? 'N/A' }}</small>
                    </td>
                </tr>
                <tr>
                    <th>Product</th>
                    <td>{{ $review->product->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <th>Rating</th>
                    <td>{{ $review->rating }} / 5</td>
                </tr>
                <tr>
                    <th>Comment</th>
                    <td>{{ $review->comment }}</td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>
                        @if($review->status == 'pending')
                            <span class="badge bg-warning">Pending</span>
                        @elseif($review->status == 'approved')
                            <span class="badge bg-success">Approved</span>
                        @else
                            <span class="badge bg-danger">Rejected</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Created At</th>
                    <td>{{ $review->created_at->format('d M, Y h:i A') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection
