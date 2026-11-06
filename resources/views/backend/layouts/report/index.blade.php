@extends('backend.master')

@section('title') Reports - List @endsection

@section('body')
<div class="page-header">
    <h1>Report List</h1>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-bordered" id="reports-table">
            <thead>
                <tr>
                    <th>SL No</th>
                    <th>User Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Message</th>
                    <th>Image</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
        </table>
</div>

<!-- Image Zoom Modal -->
<div class="modal fade" id="imageZoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body p-0 position-relative text-center">
                <button type="button" class="btn btn-danger btn-sm position-absolute d-flex align-items-center justify-content-center shadow" data-bs-dismiss="modal" style="top: -15px; right: -15px; border-radius: 50%; width: 35px; height: 35px; z-index: 1070; border: 2px solid white; background-color: #ff0000; opacity: 1;">
                    <i class="fa fa-times text-white" style="font-size: 18px;"></i>
                </button>
                
                <!-- Navigation Buttons -->
                <button type="button" id="prevImage" class="btn btn-dark btn-sm position-absolute d-flex align-items-center justify-content-center shadow" style="top: 50%; left: -50px; transform: translateY(-50%); border-radius: 50%; width: 40px; height: 40px; z-index: 1070;">
                    <i class="fa fa-chevron-left text-white"></i>
                </button>
                <button type="button" id="nextImage" class="btn btn-dark btn-sm position-absolute d-flex align-items-center justify-content-center shadow" style="top: 50%; right: -50px; transform: translateY(-50%); border-radius: 50%; width: 40px; height: 40px; z-index: 1070;">
                    <i class="fa fa-chevron-right text-white"></i>
                </button>

                <div class="p-3 bg-white shadow-lg" id="zoomContainer" style="border: 2px solid #ddd; border-radius: 8px;">
                    <img src="" id="zoomedImage" alt="Zoomed Image" style="max-height: 80vh; width: 100%; object-fit: contain; border-radius: 0px !important; transition: opacity 0.2s ease;">
                    <div id="imageCaption" class="mt-2 text-muted fw-bold"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
$(function(){
    let csrfToken = $('meta[name="csrf-token"]').attr('content');

    let table = $('#reports-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("report.data") }}',
        columns: [
            {data: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'user.name', name: 'user.name', defaultContent: 'N/A'},
            {data: 'user.email', name: 'user.email', defaultContent: 'N/A'},
            {data: 'user.phone', name: 'user.phone', defaultContent: 'N/A'},
            {data: 'message'},
            {
                data: null,
                render: function(row) {
                    let html = '<div class="d-flex flex-wrap justify-content-center gap-1">';
                    if (row.images.length > 0) {
                        row.images.forEach((img, index) => {
                            let allImages = JSON.stringify(row.images.map(i => i.image)).replace(/"/g, '&quot;');
                            html += `<img src="${img.image}" 
                                        alt="Report" 
                                        class="report-img border shadow-sm" 
                                        data-images='${allImages}'
                                        data-index="${index}"
                                        style="width: 60px; height: 60px; cursor: pointer; object-fit: cover; border-radius: 0px !important;">`;
                        });
                    } else {
                        html += `<img src="{{ asset('no_image/no_image.jpg') }}" 
                                    alt="No Image" 
                                    class="report-img border shadow-sm" 
                                    style="width: 60px; height: 60px; cursor: pointer; object-fit: cover; border-radius: 0px !important;">`;
                    }
                    html += '</div>';
                    return html;
                }
            },
            {data: 'created_at', render: d => new Date(d).toLocaleString()},
            {data: null,
                    defaultContent: '', orderable: false, searchable: false}
        ]
    });

    let currentImages = [];
    let currentIndex = 0;

    function updateZoomedImage() {
        if (currentImages.length > 0) {
            $('#zoomedImage').css('opacity', 0);
            setTimeout(() => {
                $('#zoomedImage').attr('src', currentImages[currentIndex]);
                $('#zoomedImage').css('opacity', 1);
                $('#imageCaption').text(`Image ${currentIndex + 1} of ${currentImages.length}`);
            }, 150);
            
            // Show/hide navigation buttons
            $('#prevImage').toggle(currentImages.length > 1);
            $('#nextImage').toggle(currentImages.length > 1);
        }
    }

    $(document).on('click', '.report-img', function() {
        let rawData = $(this).data('images');
        currentImages = (typeof rawData === 'string') ? JSON.parse(rawData) : (Array.isArray(rawData) ? rawData : [$(this).attr('src')]);
        currentIndex = parseInt($(this).data('index')) || 0;
        updateZoomedImage();
        $('#imageZoomModal').modal('show');
    });

    $('#prevImage').on('click', function() {
        if (currentIndex > 0) {
            currentIndex--;
        } else {
            currentIndex = currentImages.length - 1; // Loop back
        }
        updateZoomedImage();
    });

    $('#nextImage').on('click', function() {
        if (currentIndex < currentImages.length - 1) {
            currentIndex++;
        } else {
            currentIndex = 0; // Loop back
        }
        updateZoomedImage();
    });

    // Keyboard support
    $(document).on('keydown', function(e) {
        if ($('#imageZoomModal').hasClass('show')) {
            if (e.key === 'ArrowLeft') $('#prevImage').click();
            if (e.key === 'ArrowRight') $('#nextImage').click();
        }
    });

    // Mouse wheel support
    $('#zoomContainer').on('wheel', function(e) {
        if (currentImages.length > 1) {
            e.preventDefault();
            if (e.originalEvent.deltaY > 0) {
                $('#nextImage').click();
            } else {
                $('#prevImage').click();
            }
        }
    });

    $(document).on('click', '.delete-report', function() {
        let id = $(this).data('id');
        if(confirm('Are you sure you want to delete this report?')){
            $.ajax({
                url: `/report/${id}`,
                type: 'DELETE',
                data: {_token: csrfToken},
                success: function(res) {
                    if(res.status) {
                        toastr.success(res.message);
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.message);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to delete report');
                }
            });
        }
    });
});
</script>
@endpush
