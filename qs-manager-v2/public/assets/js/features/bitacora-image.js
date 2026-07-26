
// Render de la bitacora como imagen (canvas -> PNG) para mandar al equipo
// por WhatsApp. Se dibuja a mano en vez de usar una libreria porque la app
// no carga dependencias externas y el formato es fijo.

const PALETTE = {
  headerBg: '#f3eefc',
  headerText: '#5b21b6',
  columnBg: '#e6dcfa',
  columnText: '#6d28d9',
  rowBg: '#ffffff',
  rowAltBg: '#faf8ff',
  label: '#5b21b6',
  value: '#3f3f46',
  border: '#e4d9f7',
  pageBg: '#ffffff',
};

const LAYOUT = {
  width: 900,
  padding: 28,
  labelColumn: 300,
  titleHeight: 74,
  columnHeaderHeight: 58,
  lineHeight: 24,
  rowPaddingY: 18,
  fontLabel: 'bold 15px Outfit, "Segoe UI", system-ui, sans-serif',
  fontValue: '15px Outfit, "Segoe UI", system-ui, sans-serif',
  fontTitle: 'bold 22px Outfit, "Segoe UI", system-ui, sans-serif',
  fontColumn: 'bold 14px Outfit, "Segoe UI", system-ui, sans-serif',
};

function wrapText(context, text, maxWidth) {
  const paragraphs = String(text ?? '').split('\n');
  const lines = [];

  paragraphs.forEach((paragraph) => {
    const words = paragraph.split(' ').filter((word) => word !== '');
    if (!words.length) {
      lines.push('');
      return;
    }

    let current = words[0];
    for (let i = 1; i < words.length; i++) {
      const candidate = `${current} ${words[i]}`;
      if (context.measureText(candidate).width > maxWidth) {
        lines.push(current);
        current = words[i];
      } else {
        current = candidate;
      }
    }
    lines.push(current);
  });

  return lines;
}

/**
 * @param {{titulo: string, filas: Array<{campo: string, valor: string}>}} data
 * @returns {HTMLCanvasElement}
 */
export function buildBitacoraImage(data) {
  const measure = document.createElement('canvas').getContext('2d');
  measure.font = LAYOUT.fontValue;

  const valueWidth = LAYOUT.width - LAYOUT.labelColumn - LAYOUT.padding * 2;
  const rows = data.filas.map((fila) => {
    const lines = wrapText(measure, fila.valor, valueWidth);
    return { ...fila, lines, height: lines.length * LAYOUT.lineHeight + LAYOUT.rowPaddingY * 2 };
  });

  const bodyHeight = rows.reduce((sum, row) => sum + row.height, 0);
  const height = LAYOUT.titleHeight + LAYOUT.columnHeaderHeight + bodyHeight + LAYOUT.padding;

  const ratio = window.devicePixelRatio || 2;
  const canvas = document.createElement('canvas');
  canvas.width = LAYOUT.width * ratio;
  canvas.height = height * ratio;
  const ctx = canvas.getContext('2d');
  ctx.scale(ratio, ratio);
  ctx.textBaseline = 'top';

  ctx.fillStyle = PALETTE.pageBg;
  ctx.fillRect(0, 0, LAYOUT.width, height);

  // Titulo
  ctx.fillStyle = PALETTE.headerBg;
  ctx.fillRect(0, 0, LAYOUT.width, LAYOUT.titleHeight);
  ctx.fillStyle = PALETTE.headerText;
  ctx.font = LAYOUT.fontTitle;
  ctx.fillText(data.titulo, LAYOUT.padding, 24);

  // Encabezado de columnas
  let y = LAYOUT.titleHeight;
  ctx.fillStyle = PALETTE.columnBg;
  ctx.fillRect(0, y, LAYOUT.width, LAYOUT.columnHeaderHeight);
  ctx.fillStyle = PALETTE.columnText;
  ctx.font = LAYOUT.fontColumn;
  ctx.fillText('Campo', LAYOUT.padding, y + 20);
  ctx.fillText('Detalle', LAYOUT.labelColumn, y + 20);
  y += LAYOUT.columnHeaderHeight;

  rows.forEach((row, index) => {
    ctx.fillStyle = index % 2 === 0 ? PALETTE.rowBg : PALETTE.rowAltBg;
    ctx.fillRect(0, y, LAYOUT.width, row.height);

    ctx.strokeStyle = PALETTE.border;
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(0, y + 0.5);
    ctx.lineTo(LAYOUT.width, y + 0.5);
    ctx.stroke();

    ctx.fillStyle = PALETTE.label;
    ctx.font = LAYOUT.fontLabel;
    ctx.fillText(`▸ ${row.campo}`, LAYOUT.padding, y + LAYOUT.rowPaddingY);

    ctx.fillStyle = PALETTE.value;
    ctx.font = LAYOUT.fontValue;
    row.lines.forEach((line, lineIndex) => {
      ctx.fillText(line, LAYOUT.labelColumn, y + LAYOUT.rowPaddingY + lineIndex * LAYOUT.lineHeight);
    });

    y += row.height;
  });

  ctx.strokeStyle = PALETTE.border;
  ctx.beginPath();
  ctx.moveTo(0, y + 0.5);
  ctx.lineTo(LAYOUT.width, y + 0.5);
  ctx.stroke();

  return canvas;
}
