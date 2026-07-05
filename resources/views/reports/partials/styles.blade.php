{{-- Shared stylesheet for every Fronda CMMS PDF report. Import once per document
     via @include('reports.partials.styles') inside <style>; do not redefine these
     rules locally — that duplication is exactly what let the five templates drift. --}}
{{-- DomPDF's fixed header/footer only draws correctly with "top: 0" / "bottom:
     0" (they escape normal flow and stick to the true page edges) plus a body
     margin-top/margin-bottom that reserves room for them. The previous
     "top: -Npx" negative-offset technique places the header above the
     physical page — it silently never rendered in any report, before or
     after this sprint's other changes. --}}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; background: #fff;
    margin-top: 100px; margin-bottom: 46px;
    padding-left: 28px; padding-right: 28px;
}

{{-- #header/#footer are position:fixed spanning the full page width (left:0;
     right:0), so they need their own horizontal padding to line up with the
     body's — padding on body alone would leave them flush against the
     physical page edge. --}}
#header { position: fixed; top: 0; left: 0; right: 0; height: 100px; padding: 0 28px; }
#footer { position: fixed; bottom: 0; left: 0; right: 0; height: 46px; padding: 0 28px; }
{{-- counter(pages) (the document's TOTAL page count) is not supported by this
     DomPDF build without enabling embedded PHP execution in templates, which
     is not worth the added attack surface for a "Página N de M" nicety — so
     only the current page number is shown. --}}
.page:after { content: counter(page); }
.doc-body { padding-top: 0; padding-bottom: 0; }

.report-title { background: #059669; color: #fff; padding: 10px 14px; margin-bottom: 14px; border-radius: 3px; }
.report-title h1 { font-size: 15px; font-weight: bold; }
.report-title p { font-size: 9px; color: #d1fae5; margin-top: 2px; }

.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 8px; font-weight: bold; white-space: nowrap; }
.badge-success { background: #dcfce7; color: #166534; }
.badge-warning { background: #fef9c3; color: #854d0e; }
.badge-danger  { background: #fee2e2; color: #991b1b; }
.badge-info    { background: #dbeafe; color: #1e40af; }
.badge-gray    { background: #f1f5f9; color: #475569; }

.section { margin-bottom: 14px; page-break-inside: avoid; }
.section-title { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em;
                 color: #047857; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; margin-bottom: 7px; }

.grid-2 { width: 100%; }
.grid-2 td { width: 50%; vertical-align: top; padding: 0 6px 0 0; }

.field-label { font-size: 8px; color: #64748b; margin-bottom: 1px; }
.field-value { font-size: 10px; color: #1e293b; margin-bottom: 8px; }

table.data-table { width: 100%; border-collapse: collapse; font-size: 9px; }
table.data-table th { background: #f1f5f9; color: #475569; text-align: left; padding: 4px 6px;
                      font-weight: bold; border: 1px solid #e2e8f0; font-size: 8px; }
table.data-table td { padding: 4px 6px; border: 1px solid #e2e8f0; vertical-align: top; }
table.data-table tr:nth-child(even) td { background: #f8fafc; }
table.data-table tr:hover td { background: #eff6ff; }

.cost-total { background: #059669; color: #fff; padding: 6px 10px; text-align: right;
              font-size: 11px; font-weight: bold; margin-top: 4px; border-radius: 3px; }

.signature-box { border: 1px solid #e2e8f0; padding: 8px; min-height: 50px; text-align: center;
                 border-radius: 3px; }
.signature-name { font-size: 8px; color: #475569; margin-top: 4px; border-top: 1px solid #e2e8f0;
                  padding-top: 3px; }

.text-block { font-size: 9px; line-height: 1.5; color: #374151; background: #f8fafc;
              border: 1px solid #e2e8f0; border-radius: 3px; padding: 6px; }

.empty { color: #94a3b8; font-style: italic; }

.kpi-grid { width: 100%; margin-bottom: 14px; border-collapse: separate; border-spacing: 4px; }
table.kpi-grid td { width: 16.6%; padding: 0 4px 0 0; vertical-align: top; }
.kpi-box { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 3px; padding: 8px; text-align: center; }
.kpi-value { font-size: 16px; font-weight: bold; color: #047857; }
.kpi-label { font-size: 8px; color: #64748b; margin-top: 2px; }

.summary-box { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 3px; padding: 8px 12px;
               margin-bottom: 12px; }
.summary-box table td { padding: 0 16px 0 0; }
.summary-stat { font-size: 16px; font-weight: bold; color: #047857; }
.summary-label { font-size: 8px; color: #64748b; }

.stock-low { color: #dc2626; font-weight: bold; }
.stock-ok  { color: #16a34a; }

.avail-bar-outer { width: 60px; height: 7px; background: #e2e8f0; border-radius: 3px; display: inline-block; vertical-align: middle; }
.avail-bar-inner { height: 7px; border-radius: 3px; }
.bar-high   { background: #16a34a; }
.bar-medium { background: #d97706; }
.bar-low    { background: #dc2626; }

.task-num { width: 28px; font-weight: bold; color: #047857; }
.checkbox-col { width: 20px; text-align: center; }
.checkbox { display: inline-block; width: 10px; height: 10px; border: 1px solid #94a3b8; border-radius: 2px; }
