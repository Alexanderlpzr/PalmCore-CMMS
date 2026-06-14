<?php

namespace App\Domain\Reports\Contracts;

interface PdfReport
{
    /** Generate PDF and return raw bytes. */
    public function generate(string $tenantId, ?string $recordId = null): string;

    /** Return a human-friendly filename for the download. */
    public function filename(string $tenantId, ?string $recordId = null): string;
}
