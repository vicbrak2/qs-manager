<?php

declare(strict_types=1);

namespace QSManager\Application\Sheets;

use QSManager\Application\Sheets\SyncRunRepository;

final class ProcessSheetSyncRun
{
    public function __construct(
        private readonly SheetReplicaImporter $importer,
        private readonly SyncRunRepository $repository,
        private readonly string $workerId,
    ) {
    }

    public function processNext(): bool
    {
        $runId = $this->repository->claimNextRun($this->workerId);
        
        if ($runId === null) {
            return false;
        }

        try {
            $result = $this->importer->importAll($runId, function () use ($runId) {
                $this->repository->heartbeat($runId);
            });
            
            $payload = $result->toArray();
            $sources = $payload['sources'] ?? [];
            $totalSources = count($sources);
            $completedSources = 0;
            $failedSources = 0;
            $totalRowsSeen = 0;
            $totalRowsImported = 0;
            $errorSummary = [];
            
            foreach ($sources as $sheet => $data) {
                if ($data['status'] === 'failed') {
                    $failedSources++;
                    $errorSummary[] = $sheet . ': ' . $data['message'];
                } else {
                    $completedSources++;
                }
                $totalRowsSeen += $data['rows_seen'];
                $totalRowsImported += $data['rows_imported'];
            }
            
            $status = 'completed';
            if ($failedSources > 0) {
                $status = $completedSources === 0 ? 'failed' : 'partial';
            }
            if ($totalSources === 0) {
                $status = 'failed';
                $errorSummary[] = 'No sources were returned by the importer.';
            }

            $this->repository->markCompleted($runId, [
                'status' => $status,
                'totalSources' => $totalSources,
                'completedSources' => $completedSources,
                'failedSources' => $failedSources,
                'totalRowsSeen' => $totalRowsSeen,
                'totalRowsImported' => $totalRowsImported,
                'errorSummary' => $errorSummary !== [] ? implode("\n", $errorSummary) : null,
            ]);
            
        } catch (\Throwable $e) {
            $this->repository->markFailed($runId, $e->getMessage());
        }
        
        return true;
    }
}
