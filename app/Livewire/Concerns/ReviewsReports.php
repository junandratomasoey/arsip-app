<?php

namespace App\Livewire\Concerns;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentFileVersion;
use App\Models\DocumentReview;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aksi review laporan yang dipakai bersama oleh halaman Dokumen Pekerjaan
 * dan halaman Laporan & Revisi: ajukan review, setujui, minta revisi.
 * Markup modalnya ada di resources/views/reports/review-modal.blade.php.
 */
trait ReviewsReports
{
    public ?string $reviewingVersionId = null;
    public string $reviewDecision = DocumentReview::ACTION_APPROVED;
    public string $reviewNotes = '';

    /**
     * Komponen boleh mempersempit versi mana yang boleh disentuh
     * (mis. halaman Dokumen hanya versi milik pekerjaan yang dibuka).
     */
    protected function scopeReviewableVersions(Builder $query): Builder
    {
        return $query;
    }

    private function findReportVersion(string $versionId): DocumentFileVersion
    {
        $query = DocumentFileVersion::query()
            ->whereHas('documentFile.document', fn ($q) => $q->where('category', Document::CATEGORY_REPORT));

        return $this->scopeReviewableVersions($query)->with('documentFile.document')->findOrFail($versionId);
    }

    public function submitForReview(string $versionId): void
    {
        abort_unless(auth()->user()->can('document.update'), 403);

        $version = $this->findReportVersion($versionId);
        abort_unless(in_array($version->review_status, [DocumentFileVersion::REVIEW_DRAFT, null], true), 422);

        $version->submitForReview(auth()->id());

        $document = $version->documentFile->document;
        AuditLog::record('report.submitted', $document, 'Ajukan review laporan: ' . $document->title . ' (' . $version->documentFile->label . ' v' . $version->version_number . ')');

        session()->flash('status', 'Laporan diajukan untuk direview.');
    }

    public function startReview(string $versionId, string $decision): void
    {
        abort_unless(auth()->user()->can('report.review'), 403);

        $version = $this->findReportVersion($versionId);
        abort_unless($version->review_status === DocumentFileVersion::REVIEW_PENDING, 422);

        $this->resetValidation();
        $this->reviewingVersionId = $version->id;
        $this->reviewDecision = $decision === DocumentReview::ACTION_REVISION ? DocumentReview::ACTION_REVISION : DocumentReview::ACTION_APPROVED;
        $this->reviewNotes = '';
    }

    public function cancelReview(): void
    {
        $this->reviewingVersionId = null;
    }

    public function saveReview(): void
    {
        abort_unless(auth()->user()->can('report.review'), 403);

        $this->validate([
            'reviewDecision' => 'required|in:' . DocumentReview::ACTION_APPROVED . ',' . DocumentReview::ACTION_REVISION,
            'reviewNotes' => ($this->reviewDecision === DocumentReview::ACTION_REVISION ? 'required' : 'nullable') . '|string|max:5000',
        ], [
            'reviewNotes.required' => 'Tuliskan catatan revisi supaya penyusun tahu apa yang harus diperbaiki.',
        ]);

        $version = $this->findReportVersion($this->reviewingVersionId);
        abort_unless($version->review_status === DocumentFileVersion::REVIEW_PENDING, 422);

        $version->recordReview($this->reviewDecision, auth()->id(), $this->reviewNotes !== '' ? $this->reviewNotes : null);

        $document = $version->documentFile->document;
        $approved = $this->reviewDecision === DocumentReview::ACTION_APPROVED;
        AuditLog::record(
            $approved ? 'report.approved' : 'report.revision_requested',
            $document,
            ($approved ? 'Setujui laporan: ' : 'Minta revisi laporan: ') . $document->title . ' (' . $version->documentFile->label . ' v' . $version->version_number . ')',
            $this->reviewNotes !== '' ? ['notes' => $this->reviewNotes] : []
        );

        $this->reviewingVersionId = null;
        session()->flash('status', $approved ? 'Laporan disetujui.' : 'Catatan revisi dikirim ke penyusun laporan.');
    }
}
