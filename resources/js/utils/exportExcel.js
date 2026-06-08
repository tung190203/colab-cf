import * as XLSX from 'xlsx';

function normalizeFileName(name) {
    return String(name || 'export')
        .trim()
        .replace(/[\\/:*?"<>|]+/g, '-')
        .replace(/\s+/g, '_');
}

function valueForColumn(column, row) {
    return typeof column.value === 'function' ? column.value(row) : row[column.key];
}

function fitColumns(columns, rows) {
    return columns.map(column => {
        const headerLength = String(column.header || '').length;
        const maxValueLength = rows.reduce((max, row) => {
            const value = valueForColumn(column, row);
            return Math.max(max, String(value ?? '').length);
        }, headerLength);

        return { wch: Math.min(Math.max(maxValueLength + 2, 10), 42) };
    });
}

function applyColumnFormats(worksheet, columns, rowCount) {
    columns.forEach((column, colIndex) => {
        if (!column.numberFormat) return;

        for (let rowIndex = 1; rowIndex <= rowCount; rowIndex++) {
            const cellAddress = XLSX.utils.encode_cell({ c: colIndex, r: rowIndex });
            const cell = worksheet[cellAddress];

            if (cell && typeof cell.v === 'number') {
                cell.z = column.numberFormat;
            }
        }
    });
}

export function exportRowsToExcel({ columns, rows, fileName, sheetName }) {
    const data = rows.map(row => {
        return columns.reduce((item, column) => {
            item[column.header] = valueForColumn(column, row) ?? '';
            return item;
        }, {});
    });

    const worksheet = XLSX.utils.json_to_sheet(data, {
        header: columns.map(column => column.header),
    });
    worksheet['!cols'] = fitColumns(columns, rows);
    applyColumnFormats(worksheet, columns, rows.length);

    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, String(sheetName || 'Sheet1').slice(0, 31));
    XLSX.writeFile(workbook, `${normalizeFileName(fileName || sheetName)}.xlsx`, {
        bookType: 'xlsx',
        compression: true,
    });
}
