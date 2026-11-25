<?php

namespace App\Http\Controllers;

use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Services\FileStorage;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Private files are streamed only to authorized users of the same company
 * (customers only for their own records).
 */
class AttachmentController extends Controller
{
    public function show(Request $request, Attachment $attachment)
    {
        $this->authorizeAccess($request, $attachment);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => ($attachment->isImage() || $attachment->isVideo() || $attachment->mime === 'application/pdf' ? 'inline' : 'attachment').'; filename="'.addslashes($attachment->original_name).'"',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'attachable_type' => ['required', 'string'],
            'attachable_id' => ['required', 'integer'],
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'max:'.config('erp.uploads.max_kb'), 'mimes:'.config('erp.uploads.mimes')],
            'category' => ['nullable', 'string', 'max:50'],
            'caption' => ['nullable', 'string', 'max:190'],
        ]);
        $class = Relation::getMorphedModel($request->input('attachable_type'));
        abort_unless($class, 422);
        $model = $class::query()->findOrFail($request->integer('attachable_id')); // tenant scoped
        foreach ($request->file('files') as $file) {
            FileStorage::attach($model, $file, $request->input('category'), $request->input('caption'));
        }

        return back()->with('success', __('Files uploaded.'));
    }

    public function destroy(Request $request, Attachment $attachment)
    {
        $this->authorizeAccess($request, $attachment);
        abort_if($request->user()->isCustomer(), 403);
        Storage::disk($attachment->disk)->delete($attachment->path);
        AuditLogger::log('attachment.deleted', $attachment, ['name' => $attachment->original_name]);
        $attachment->delete();

        return back()->with('success', __('Deleted successfully.'));
    }

    protected function authorizeAccess(Request $request, Attachment $attachment): void
    {
        $user = $request->user();
        abort_unless($user && ($user->is_super_admin || $attachment->company_id === tenant()->id()), 404);
        if ($user->isCustomer()) {
            $owner = $attachment->attachable;
            $customerId = $owner?->customer_id ?? $owner?->jobCard?->customer_id ?? null;
            abort_unless($customerId === $user->customer_id, 403);
        }
    }
}
