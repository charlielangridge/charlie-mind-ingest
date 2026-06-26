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
        private CaptureReviewRouter $reviewRouter,
        private ProcessedNoteRenderer $renderer,
        private ProcessedNotePathGenerator $pathGenerator,
        private ReviewIndexWriter $reviewIndexWriter,
        private CharlieMindStorage $storage,
        private CaptureStagingPaths $stagingPaths,
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
            $reviewRoute = $this->reviewRouter->route($capture, $result);
            $suggestedPath = $this->pathGenerator->pathFor($capture, $result, $reviewRoute->folder);
            $processedPath = $this->pathGenerator->pathFor(
                $capture,
                $result,
                $this->stagingPaths->stagedProcessedFolder($result->confidence),
            );
            $stagedRawPath = $this->stagedRawPath($capture);
            $stagedMediaPath = $this->stagedMediaPath($capture);

            if ($dryRun) {
                return new CaptureProcessingOutcome(
                    capture: $capture,
                    status: 'dry-run',
                    processedPath: $processedPath,
                    needsReview: $reviewRoute->needsReview,
                    reviewReason: $reviewRoute->reviewReason,
                );
            }

            $this->copyIfAvailable($capture->markdown_path, $stagedRawPath);
            $this->copyIfAvailable($capture->media_path, $stagedMediaPath);

            $this->storage->putVaultFile(
                $processedPath,
                $this->renderer->render(
                    $capture,
                    $content,
                    $result,
                    $reviewRoute,
                    $suggestedPath,
                    $stagedRawPath,
                    $stagedMediaPath,
                ),
            );

            $this->reviewIndexWriter->append($capture, $result, $reviewRoute, $processedPath);

            $capture->forceFill([
                'status' => Capture::STATUS_PROCESSED,
                'processed_markdown_path' => $processedPath,
                'processed_at' => now(),
                'summary' => $result->summary,
                'suggested_title' => $result->title,
                'suggested_folder' => $reviewRoute->folder,
                'suggested_path' => $suggestedPath,
                'suggested_tags' => $reviewRoute->tags,
                'media_path' => $stagedMediaPath ?? $capture->media_path,
                'needs_review' => $reviewRoute->needsReview,
                'review_reason' => $reviewRoute->reviewReason,
                'transcript' => $content->transcript,
                'processing_error' => null,
            ])->save();

            return new CaptureProcessingOutcome(
                capture: $capture,
                status: Capture::STATUS_PROCESSED,
                processedPath: $processedPath,
                needsReview: $reviewRoute->needsReview,
                reviewReason: $reviewRoute->reviewReason,
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

    private function stagedRawPath(Capture $capture): ?string
    {
        if ($capture->markdown_path === null) {
            return null;
        }

        return $this->stagingPaths->rawPath($capture);
    }

    private function stagedMediaPath(Capture $capture): ?string
    {
        return $this->stagingPaths->mediaPath($capture);
    }

    private function copyIfAvailable(?string $from, ?string $to): void
    {
        if ($from === null || $to === null || $from === $to || ! $this->storage->exists($from)) {
            return;
        }

        $this->storage->copyVaultFile($from, $to);
    }
}
