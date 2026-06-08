function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/\n/g, '<br>');
}

function normalizeFileName(name) {
    return String(name || 'export')
        .trim()
        .replace(/[\\/:*?"<>|]+/g, '-')
        .replace(/\s+/g, '_');
}

export function exportRowsToExcel({ columns, rows, fileName, sheetName }) {
    const headerHtml = columns
        .map(column => `<th>${escapeHtml(column.header)}</th>`)
        .join('');

    const bodyHtml = rows
        .map(row => {
            const cells = columns.map(column => {
                const value = typeof column.value === 'function' ? column.value(row) : row[column.key];
                return `<td>${escapeHtml(value)}</td>`;
            }).join('');

            return `<tr>${cells}</tr>`;
        })
        .join('');

    const html = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">
            <head>
                <meta charset="UTF-8" />
                <style>
                    table { border-collapse: collapse; }
                    th, td { border: 1px solid #d9d9d9; padding: 6px 8px; font-family: Arial, sans-serif; font-size: 11pt; }
                    th { background: #f3f4f6; font-weight: 700; }
                </style>
            </head>
            <body>
                <table>
                    <thead><tr>${headerHtml}</tr></thead>
                    <tbody>${bodyHtml}</tbody>
                </table>
            </body>
        </html>
    `;

    const blob = new Blob(['\ufeff', html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${normalizeFileName(fileName || sheetName)}.xls`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
