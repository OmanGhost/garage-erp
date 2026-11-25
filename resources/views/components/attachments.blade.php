@props(['model', 'title' => null, 'category' => null, 'canUpload' => true, 'accept' => null])
@php $files = $model->attachments()->when($category, fn($q) => $q->where('category', $category))->get(); @endphp
<div class="card mb-3">
    <div class="card-header d-flex align-items-center"><i class="bi bi-paperclip me-1"></i>{{ $title ?? __('Photos & attachments') }} <span class="badge text-bg-secondary ms-2">{{ $files->count() }}</span></div>
    <div class="card-body">
        @if($files->isEmpty())
            <div class="text-body-secondary small">{{ __('No files yet.') }}</div>
        @else
            <div class="row g-2">
                @foreach($files as $f)
                    <div class="col-6 col-md-3 col-xl-2">
                        <div class="border rounded p-1 h-100 position-relative">
                            @if($f->isImage())
                                <a href="{{ $f->url() }}" target="_blank"><img src="{{ $f->url() }}" class="w-100 rounded" style="height:110px;object-fit:cover" alt="{{ $f->original_name }}" loading="lazy"></a>
                            @elseif($f->isVideo())
                                <video src="{{ $f->url() }}" class="w-100 rounded" style="height:110px" controls preload="none"></video>
                            @else
                                <a href="{{ $f->url() }}" target="_blank" class="d-flex flex-column align-items-center justify-content-center text-decoration-none" style="height:110px"><i class="bi bi-file-earmark-text fs-1"></i><span class="small text-truncate w-100 text-center">{{ $f->original_name }}</span></a>
                            @endif
                            <div class="small text-truncate mt-1" title="{{ $f->caption ?? $f->original_name }}">{{ $f->caption ?? $f->original_name }}</div>
                            @if($canUpload && !auth()->user()->isCustomer())
                                <form method="post" action="{{ route('attachments.destroy', $f) }}" class="position-absolute top-0 end-0 m-1" data-confirm="{{ __('Delete this file?') }}">@csrf @method('delete')
                                    <button class="btn btn-sm btn-light py-0 px-1"><i class="bi bi-trash text-danger"></i></button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        @if($canUpload)
            <form method="post" action="{{ route('attachments.store') }}" enctype="multipart/form-data" class="row g-2 mt-2 align-items-end">
                @csrf
                <input type="hidden" name="attachable_type" value="{{ $model->getMorphClass() }}">
                <input type="hidden" name="attachable_id" value="{{ $model->getKey() }}">
                <input type="hidden" name="category" value="{{ $category }}">
                <div class="col-md-5"><input type="file" name="files[]" multiple class="form-control form-control-sm" @if($accept) accept="{{ $accept }}" @endif capture="environment" required></div>
                <div class="col-md-5"><input type="text" name="caption" class="form-control form-control-sm" placeholder="{{ __('Caption (optional)') }}"></div>
                <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-upload"></i> {{ __('Upload') }}</button></div>
            </form>
        @endif
    </div>
</div>
