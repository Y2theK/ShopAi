import DOMPurify from 'dompurify'

// The renderer only ever emits these tags; DOMPurify strips anything else
// as a safety net for future changes to the formatting logic below.
const ALLOWED_TAGS = ['p', 'br', 'table', 'tr', 'th', 'td', 'strong', 'em', 'code', 'span']
const ALLOWED_ATTR = ['class']

export function renderMarkdown(text: string): string {
  return DOMPurify.sanitize(render(text), { ALLOWED_TAGS, ALLOWED_ATTR })
}

function render(text: string): string {
  const lines = text.split('\n')
  const out: string[] = []
  let i = 0

  while (i < lines.length) {
    const line = lines[i] ?? ''

    // Table: line starts with |
    if (line.trimStart().startsWith('|')) {
      const tableLines: string[] = []
      while (i < lines.length && (lines[i] ?? '').trimStart().startsWith('|')) {
        tableLines.push(lines[i] ?? '')
        i++
      }
      // filter separator rows (---|---)
      const rows = tableLines.filter((l) => !/^\s*\|[\s|:-]+\|\s*$/.test(l))
      if (rows.length) {
        out.push('<table>')
        rows.forEach((row, idx) => {
          const cells = row
            .replace(/^\s*\|/, '')
            .replace(/\|\s*$/, '')
            .split('|')
            .map((c) => c.trim())
          const tag = idx === 0 ? 'th' : 'td'
          out.push(`<tr>${cells.map((c) => `<${tag}>${formatCell(c, idx)}</${tag}>`).join('')}</tr>`)
        })
        out.push('</table>')
      }
      continue
    }

    out.push(line === '' ? '<br>' : `<p>${inlineFormat(line)}</p>`)
    i++
  }

  return out.join('')
}

function formatCell(text: string, rowIndex: number): string {
  if (rowIndex > 0) {
    const trimmed = text.trim()
    if (/^in stock$/i.test(trimmed)) {
      return `<span class="stock-in">In Stock</span>`
    }
    if (/^out of stock$/i.test(trimmed)) {
      return `<span class="stock-out">Out of Stock</span>`
    }
  }
  return inlineFormat(text)
}

function inlineFormat(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.+?)\*/g, '<em>$1</em>')
    .replace(/`(.+?)`/g, '<code>$1</code>')
}
