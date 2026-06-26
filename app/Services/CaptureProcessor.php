<?php

namespace App\Services;

use App\Models\Capture;
use Throwable;

class CaptureProcessor
{
    public function __construct(
        private CaptureContentExtractor $extractor,
        private CaptureTranscriber $transcriber,
        private CaptureAiProcessor $aiProcessor,
        private ProcessedNoteRenderer $renderer,
        private ProcessedNotePathGenerator $pathGenerator,
        private CharlieMindStorage $storage,
    ) {}

    public function process(Capture $capture, bool $dryRun = false): CaptureProcessingOutcome
    {
        try {
            if (! $dryRun) {
                $capture->forceFill([
                    'status' => Capture::STATUS_PROCESSING,
                    'processing_started_at' => now(),
                    'processing_attempts' => ((int) $capture->processing_attempts) + 1,
                    'processing_error' => null,
                ])->save();
            }

            $content = $this->extractor->extract($capture);

            if ($capture->type === Capture::TYPE_VOICE && $capture->media_path !== null) {
                $content->transcript = $this->transcriber->transcribe($capture->media_path);
            }

            $result = $this->aiProcessor->process($capture, $content);
            $processedPath = $this->pathGenerator->pathFor($capture, $result);

            if ($dryRun) {
                return new CaptureProcessingOutcome(
                    capture: $capture,
                    status: 'dry-run',
                    processedPath: $processedPath,
                );
            }

            $this->storage->putVaultFile(
                $processedPath,
                $this->renderer->render($capture, $content, $result),
            );

            $capture->forceFill([
                'status' => Capture::STATUS_PROCESSED,
                'processed_markdown_path' => $processedPath,
                'processed_at' => now(),
                'summary' => $result->summary,
                'suggested_title' => $result->title,
                'suggested_tags' => $result->tags,
                'transcript' => $content->transcript,
                'processing_error' => null,
            ])->save();

            return new CaptureProcessingOutcome(
                capture: $capture,
                status: Capture::STATUS_PROCESSED,
                processedPath: $processedPath,
            );
        } catch (Throwable $throwable) {
            if (! $dryRun) {
                $capture->forceFill([
                    'status' => Capture::STATUS_FAILED,
                    'processing_error' => $throwable->getMessage(),
                ])->save();
            }

            return new CaptureProcessingOutcome(
                capture: $capture,
                status: Capture::STATUS_FAILED,
                message: $throwable->getMessage(),
            );
        }
    }
}
