/**
 * Export utilities for downloading data in various formats
 */

/**
 * Download data as JSON file
 */
export function downloadJSON(data, filename = 'export.json') {
  const json = JSON.stringify(data, null, 2);
  const blob = new Blob([json], { type: 'application/json' });
  downloadBlob(blob, filename);
}

/**
 * Download data as CSV file
 */
export function downloadCSV(data, filename = 'export.csv', headers = null) {
  if (!Array.isArray(data) || data.length === 0) {
    console.warn('No data to export');
    return;
  }

  // Get headers from first object if not provided
  const csvHeaders = headers || Object.keys(data[0]);

  // Create CSV content
  let csv = csvHeaders.join(',') + '\n';

  data.forEach(row => {
    const values = csvHeaders.map(header => {
      const value = row[header];
      // Escape values containing commas or quotes
      if (value === null || value === undefined) return '';
      const stringValue = String(value);
      if (stringValue.includes(',') || stringValue.includes('"') || stringValue.includes('\n')) {
        return `"${stringValue.replace(/"/g, '""')}"`;
      }
      return stringValue;
    });
    csv += values.join(',') + '\n';
  });

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  downloadBlob(blob, filename);
}

/**
 * Download data as HTML report
 */
export function downloadHTMLReport(data, filename = 'report.html') {
  const html = generateHTMLReport(data);
  const blob = new Blob([html], { type: 'text/html;charset=utf-8;' });
  downloadBlob(blob, filename);
}

/**
 * Download blob as file
 */
function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

/**
 * Generate HTML report from data
 */
function generateHTMLReport(data) {
  const { status, migrations, schema, metrics, history } = data;
  const now = new Date().toLocaleString();

  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Migration Report - ${now}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      line-height: 1.6;
      color: #1f2937;
      padding: 2rem;
      background: #f9fafb;
    }
    .container { max-width: 1200px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    h1 { color: #111827; margin-bottom: 1rem; font-size: 2rem; }
    h2 { color: #374151; margin-top: 2rem; margin-bottom: 1rem; font-size: 1.5rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.5rem; }
    h3 { color: #4b5563; margin-top: 1.5rem; margin-bottom: 0.5rem; font-size: 1.25rem; }
    .header { border-bottom: 3px solid #3b82f6; padding-bottom: 1rem; margin-bottom: 2rem; }
    .badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.875rem;
      font-weight: 500;
      margin-right: 0.5rem;
    }
    .badge-safe { background: #d1fae5; color: #065f46; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .badge-info { background: #dbeafe; color: #1e40af; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 1rem 0; }
    .stat-card { padding: 1rem; background: #f9fafb; border-radius: 6px; border-left: 4px solid #3b82f6; }
    .stat-value { font-size: 2rem; font-weight: bold; color: #111827; }
    .stat-label { font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem; }
    table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
    th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
    th { background: #f9fafb; font-weight: 600; color: #374151; }
    tr:hover { background: #f9fafb; }
    .footer { margin-top: 3rem; padding-top: 1rem; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 0.875rem; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>🛡️ Smart Migration Report</h1>
      <p>Generated: ${now}</p>
      <p>Environment: ${status?.environment || 'N/A'} | Database: ${status?.database_driver || 'N/A'}</p>
    </div>

    <section>
      <h2>Overview</h2>
      <div class="stats">
        <div class="stat-card">
          <div class="stat-value">${status?.pending_count || 0}</div>
          <div class="stat-label">Pending Migrations</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">${status?.applied_count || 0}</div>
          <div class="stat-label">Applied Migrations</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">${status?.table_count || 0}</div>
          <div class="stat-label">Database Tables</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">${status?.drift_detected ? 'Yes' : 'No'}</div>
          <div class="stat-label">Schema Drift Detected</div>
        </div>
      </div>
    </section>

    ${migrations && migrations.length > 0 ? `
    <section>
      <h2>Migrations</h2>
      <table>
        <thead>
          <tr>
            <th>Migration</th>
            <th>Status</th>
            <th>Risk</th>
            <th>Applied At</th>
          </tr>
        </thead>
        <tbody>
          ${migrations.map(m => `
            <tr>
              <td>${m.name}</td>
              <td><span class="badge badge-${m.status === 'applied' ? 'info' : 'warning'}">${m.status}</span></td>
              <td><span class="badge badge-${m.risk}">${m.risk}</span></td>
              <td>${m.applied_at || '-'}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </section>
    ` : ''}

    ${schema?.tables && schema.tables.length > 0 ? `
    <section>
      <h2>Database Schema</h2>
      <p>Total Tables: ${schema.tables.length}</p>
      ${schema.tables.slice(0, 10).map(t => `
        <div>
          <h3>${t.name}</h3>
          <p>Columns: ${t.column_count} | Rows: ${t.row_count || 0} | Indexes: ${t.indexes?.length || 0} | Foreign Keys: ${t.foreign_keys?.length || 0}</p>
        </div>
      `).join('')}
      ${schema.tables.length > 10 ? `<p><em>... and ${schema.tables.length - 10} more tables</em></p>` : ''}
    </section>
    ` : ''}

    ${metrics?.risk_distribution ? `
    <section>
      <h2>Risk Distribution</h2>
      <div class="stats">
        <div class="stat-card">
          <div class="stat-value">${metrics.risk_distribution.safe || 0}</div>
          <div class="stat-label">Safe Migrations</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">${metrics.risk_distribution.warning || 0}</div>
          <div class="stat-label">Warning Migrations</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">${metrics.risk_distribution.danger || 0}</div>
          <div class="stat-label">Danger Migrations</div>
        </div>
      </div>
    </section>
    ` : ''}

    <div class="footer">
      <p>Generated by Smart Migration Dashboard v2.0.0</p>
    </div>
  </div>
</body>
</html>`;
}
