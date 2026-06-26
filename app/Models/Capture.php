<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class Capture extends Model
{
    public const TYPE_QUICK = 'quick';

    public const TYPE_TASK = 'task';

    public const TYPE_IDEA = 'idea';

    public const TYPE_DEV = 'dev';

    public const TYPE_SCOUTS = 'scouts';

    public const TYPE_WINE = 'wine';

    public const TYPE_LINK = 'link';

    public const TYPE_VOICE = 'voice';

    public const TYPE_PHOTO = 'photo';

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_GENERAL = 'general';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const EXPORT_STATUS_PENDING = 'pending';

    public const EXPORT_STATUS_EXPORTED = 'exported';

    public const EXPORT_STATUS_FAILED = 'failed';

    public const EXPORT_STATUS_SKIPPED = 'skipped';

    public const SUPPORTED_TYPES = [
        self::TYPE_QUICK,
        self::TYPE_TASK,
        self::TYPE_IDEA,
        self::TYPE_DEV,
        self::TYPE_SCOUTS,
        self::TYPE_WINE,
        self::TYPE_LINK,
        self::TYPE_VOICE,
        self::TYPE_PHOTO,
        self::TYPE_DOCUMENT,
        self::TYPE_GENERAL,
    ];

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
        self::STATUS_PROCESSED,
        self::STATUS_FAILED,
        self::STATUS_SKIPPED,
    ];

    public const EXPORT_STATUSES = [
        self::EXPORT_STATUS_PENDING,
        self::EXPORT_STATUS_EXPORTED,
        self::EXPORT_STATUS_FAILED,
        self::EXPORT_STATUS_SKIPPED,
    ];

    protected $fillable = [
        'capture_id',
        'type',
        'title',
        'body',
        'url',
        'source',
        'status',
        'processing_error',
        'processing_attempts',
        'processing_started_at',
        'markdown_path',
        'processed_markdown_path',
        'media_path',
        'media_mime',
        'media_original_name',
        'metadata',
        'captured_at',
        'processed_at',
        'transcript',
        'summary',
        'suggested_title',
        'suggested_tags',
        'needs_review',
        'review_reason',
        'reviewed_at',
        'export_status',
        'exported_at',
        'export_attempts',
        'export_error',
        'last_export_attempt_at',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'processing_attempts' => 0,
        'needs_review' => false,
        'export_attempts' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'suggested_tags' => 'array',
            'captured_at' => 'datetime',
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
            'needs_review' => 'boolean',
            'reviewed_at' => 'datetime',
            'exported_at' => 'datetime',
            'last_export_attempt_at' => 'datetime',
        ];
    }

    public static function normalizeType(?string $type): string
    {
        $normalizedType = str($type ?? self::TYPE_GENERAL)->lower()->trim()->toString();

        if ($normalizedType === 'tasks') {
            return self::TYPE_TASK;
        }

        if ($normalizedType === 'ideas') {
            return self::TYPE_IDEA;
        }

        if ($normalizedType === 'links') {
            return self::TYPE_LINK;
        }

        return Arr::first(
            self::SUPPORTED_TYPES,
            fn (string $supportedType): bool => $supportedType === $normalizedType,
            self::TYPE_GENERAL,
        );
    }
}
