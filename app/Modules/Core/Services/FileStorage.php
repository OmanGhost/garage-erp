<?php

namespace App\Modules\Core\Services;

use App\Exceptions\BusinessException;
use App\Modules\Core\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Secure uploads: extension + real MIME whitelist, size limit, random names,
 * private disk (served through an authorized controller), storage quota per tenant.
 */
class FileStorage
{
    public const BLOCKED = ['php', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm', 'svg', 'xml'];

    public static function attach(Model $model, UploadedFile $file, ?string $category = null, ?string $caption = null): Attachment
    {
        self::validate($file);
        self::assertQuota($file->getSize());
        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        $path = $file->storeAs('attachments/'.tenant()->id().'/'.now()->format('Y/m'), Str::uuid().'.'.$ext, 'local');

        return Attachment::create([
            'attachable_type' => $model->getMorphClass(),
            'attachable_id' => $model->getKey(),
            'disk' => 'local',
            'path' => $path,
            'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'category' => $category,
            'caption' => $caption,
            'uploaded_by' => auth()->id(),
        ]);
    }

    /** Public images (logos, part photos) stored on the public disk. */
    public static function storePublicImage(UploadedFile $file, string $folder): string
    {
        self::validate($file, true);

        return $file->storeAs($folder.'/'.tenant()->id(), Str::uuid().'.'.strtolower($file->guessExtension() ?: 'jpg'), 'public');
    }

    public static function validate(UploadedFile $file, bool $imageOnly = false): void
    {
        if (! $file->isValid()) {
            throw new BusinessException(__('Upload failed.'));
        }
        $ext = strtolower($file->getClientOriginalExtension());
        $guessed = strtolower((string) $file->guessExtension());
        $allowed = explode(',', $imageOnly ? config('erp.uploads.image_mimes') : config('erp.uploads.mimes'));
        if (in_array($ext, self::BLOCKED, true) || in_array($guessed, self::BLOCKED, true)) {
            throw new BusinessException(__('This file type is not allowed.'));
        }
        if (! in_array($ext, $allowed, true) || ($guessed && ! in_array($guessed === 'jpeg' ? 'jpg' : $guessed, array_merge($allowed, ['jpeg', 'qt', 'mov']), true))) {
            throw new BusinessException(__('This file type is not allowed.'));
        }
        if ($file->getSize() > config('erp.uploads.max_kb') * 1024) {
            throw new BusinessException(__('The file is too large.'));
        }
    }

    /** SaaS storage limit enforcement. */
    public static function assertQuota(int $bytes): void
    {
        $limitMb = tenant()->company()?->plan?->max_storage_mb;
        if (! $limitMb) {
            return;
        }
        $used = (int) Attachment::query()->sum('size');
        if ($used + $bytes > $limitMb * 1024 * 1024) {
            throw new BusinessException(__('Storage limit of your subscription plan has been reached.'));
        }
    }
}
