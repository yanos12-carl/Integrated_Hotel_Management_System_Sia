/* IHMS charts: dependency-free SVG charts themed with the Dusty Pink / Khaki / Cream palette. */
(function () {
  'use strict';

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const GRID = '#e6dbc6';
  const AXIS = '#a1968c';
  const INK = '#413733';

  const PALETTE = ['#c4736f', '#b3a76f', '#5d8b58', '#587a95', '#bd873c', '#8a4748', '#978b55', '#d5948e'];
  const TONE = { rose: '#c4736f', khaki: '#b3a76f', sage: '#5d8b58', sky: '#587a95', amber: '#bd873c', brick: '#b1463d', ink: '#877971' };

  let uid = 0;
  const nextId = () => 'ihmsc' + (++uid);

  function num(v) {
    const n = Number(v) || 0;
    if (Math.abs(n) >= 1000000) return (n / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
    if (Math.abs(n) >= 1000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
    return String(Math.round(n * 100) / 100);
  }

  function nice(max) {
    if (max <= 0) return 1;
    const mag = Math.pow(10, Math.floor(Math.log10(max)));
    const n = max / mag;
    const step = n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10;
    return step * mag;
  }

  function empty(msg) {
    return `<div class="empty"><span class="em">◔</span><span>${esc(msg)}</span></div>`;
  }

  /**
   * Multi-series line / area chart.
   * series: [{ name, color, points: [{ x: 'Oct 1', y: 12 }] }]
   */
  function line(series, o) {
    o = o || {};
    const sets = (series || []).filter((s) => s && Array.isArray(s.points) && s.points.length);
    if (!sets.length) return empty(o.empty || 'No activity recorded in this period yet.');

    const W = 780, H = o.height || 268;
    const P = { t: 18, r: 16, b: 32, l: 48 };
    const n = Math.max.apply(null, sets.map((s) => s.points.length));
    const max = Math.max.apply(null, [1].concat(...sets.map((s) => s.points.map((p) => Number(p.y) || 0))));
    const top = nice(max);
    const iw = W - P.l - P.r, ih = H - P.t - P.b;
    const X = (i) => P.l + (n <= 1 ? iw / 2 : (i * iw) / (n - 1));
    const Y = (v) => P.t + ih - ((Number(v) || 0) / top) * ih;
    const ticks = 4;

    let grid = '', ylab = '';
    for (let i = 0; i <= ticks; i++) {
      const v = (top / ticks) * i, y = Y(v).toFixed(1);
      grid += `<line x1="${P.l}" y1="${y}" x2="${W - P.r}" y2="${y}" stroke="${GRID}" stroke-width="1" stroke-dasharray="${i === 0 ? '0' : '3 4'}"/>`;
      ylab += `<text x="${P.l - 10}" y="${(+y + 4).toFixed(1)}" fill="${AXIS}" font-size="11" text-anchor="end">${esc(num(v))}</text>`;
    }

    const labels = sets[0].points.map((p) => p.x);
    const step = Math.max(1, Math.ceil(n / 8));
    let xlab = '';
    labels.forEach((l, i) => {
      if (i % step !== 0 && i !== n - 1) return;
      xlab += `<text x="${X(i).toFixed(1)}" y="${H - 10}" fill="${AXIS}" font-size="11" text-anchor="middle">${esc(l)}</text>`;
    });

    let body = '', legend = '';
    sets.forEach((s, si) => {
      const color = s.color || PALETTE[si % PALETTE.length];
      const pts = s.points.slice(0, n);
      const lineD = pts.map((p, i) => (i ? 'L' : 'M') + X(i).toFixed(1) + ' ' + Y(p.y).toFixed(1)).join(' ');
      const gid = nextId();
      const areaD = lineD + ' L' + X(pts.length - 1).toFixed(1) + ' ' + (P.t + ih) + ' L' + X(0).toFixed(1) + ' ' + (P.t + ih) + ' Z';

      body += `<defs><linearGradient id="${gid}" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="${color}" stop-opacity=".34"/><stop offset="100%" stop-color="${color}" stop-opacity="0"/>
      </linearGradient></defs>`;
      body += `<path d="${areaD}" fill="url(#${gid})"/>`;
      body += `<path d="${lineD}" fill="none" stroke="${color}" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>`;
      pts.forEach((p, i) => {
        body += `<circle class="tip" cx="${X(i).toFixed(1)}" cy="${Y(p.y).toFixed(1)}" r="3.4" fill="#fffdf9" stroke="${color}" stroke-width="2">
          <title>${esc(p.x)} · ${esc(s.name || '')}: ${esc(num(p.y))}</title></circle>`;
      });
      legend += `<span><i style="background:${color}"></i>${esc(s.name || 'Series ' + (si + 1))}</span>`;
    });

    return `<div class="chart-wrap"><svg viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(o.title || 'Trend chart')}">${grid}${ylab}${xlab}${body}</svg></div>` +
      (legend ? `<div class="legend">${legend}</div>` : '');
  }

  /**
   * Vertical column chart. data: [{ label, value }]
   */
  function column(data, o) {
    o = o || {};
    const rows = (data || []).filter((d) => d);
    if (!rows.length || !rows.some((d) => Number(d.value) > 0)) {
      return empty(o.empty || 'No revenue recorded in this period yet.');
    }
    const W = 780, H = o.height || 250;
    const P = { t: 18, r: 14, b: 34, l: 48 };
    const max = Math.max.apply(null, rows.map((d) => Number(d.value) || 0));
    const top = nice(max);
    const iw = W - P.l - P.r, ih = H - P.t - P.b;
    const n = rows.length;
    const slot = iw / n;
    const bw = Math.max(6, Math.min(46, slot * 0.56));
    const color = o.color || '#c4736f';

    let grid = '', ylab = '';
    for (let i = 0; i <= 4; i++) {
      const v = (top / 4) * i, y = (P.t + ih - (v / top) * ih).toFixed(1);
      grid += `<line x1="${P.l}" y1="${y}" x2="${W - P.r}" y2="${y}" stroke="${GRID}" stroke-width="1" stroke-dasharray="${i === 0 ? '0' : '3 4'}"/>`;
      ylab += `<text x="${P.l - 10}" y="${(+y + 4).toFixed(1)}" fill="${AXIS}" font-size="11" text-anchor="end">${esc(num(v))}</text>`;
    }

    const step = Math.max(1, Math.ceil(n / 8));
    let bars = '', xlab = '';
    rows.forEach((d, i) => {
      const v = Number(d.value) || 0;
      const h = top > 0 ? (v / top) * ih : 0;
      const x = P.l + slot * i + (slot - bw) / 2;
      const y = P.t + ih - h;
      const gid = nextId();
      bars += `<defs><linearGradient id="${gid}" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="${d.color || color}" stop-opacity=".95"/><stop offset="100%" stop-color="${d.color || color}" stop-opacity=".62"/>
      </linearGradient></defs>`;
      bars += `<rect class="tip" x="${x.toFixed(1)}" y="${(h > 0 ? y : P.t + ih - 2).toFixed(1)}" width="${bw.toFixed(1)}" height="${Math.max(2, h).toFixed(1)}" rx="${Math.min(6, bw / 3).toFixed(1)}" fill="url(#${gid})">
        <title>${esc(d.label)}: ${esc(num(v))}</title></rect>`;
      if (i % step === 0 || i === n - 1) {
        xlab += `<text x="${(x + bw / 2).toFixed(1)}" y="${H - 11}" fill="${AXIS}" font-size="11" text-anchor="middle">${esc(d.label)}</text>`;
      }
    });

    return `<div class="chart-wrap"><svg viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(o.title || 'Column chart')}">${grid}${ylab}${xlab}${bars}</svg></div>`;
  }

  /**
   * Donut chart. data: [{ label, value, tone|color }]
   */
  function donut(data, o) {
    o = o || {};
    const rows = (data || []).filter((d) => Number(d.value) > 0);
    if (!rows.length) return empty(o.empty || 'No data to chart yet.');
    const total = rows.reduce((s, d) => s + (Number(d.value) || 0), 0);
    const S = 210, r = 76, c = 2 * Math.PI * r, mid = S / 2;
    let off = 0, segs = '';
    rows.forEach((d, i) => {
      const v = Number(d.value) || 0;
      const frac = total > 0 ? v / total : 0;
      const color = d.color || TONE[d.tone] || PALETTE[i % PALETTE.length];
      segs += `<circle class="tip" cx="${mid}" cy="${mid}" r="${r}" fill="none" stroke="${color}" stroke-width="26"
        stroke-dasharray="${(frac * c).toFixed(2)} ${(c - frac * c).toFixed(2)}" stroke-dashoffset="${(-off * c).toFixed(2)}" transform="rotate(-90 ${mid} ${mid})">
        <title>${esc(d.label)}: ${esc(num(v))}</title></circle>`;
      off += frac;
    });
    const centre = o.centre === undefined
      ? `<text x="${mid}" y="${mid - 2}" text-anchor="middle" fill="${INK}" font-size="26" font-weight="700">${esc(num(total))}</text>
         <text x="${mid}" y="${mid + 17}" text-anchor="middle" fill="${AXIS}" font-size="11">${esc(o.centreLabel || 'Total')}</text>`
      : '';
    return `<div class="donut-flex">
      <svg viewBox="0 0 ${S} ${S}" style="width:190px;max-width:44%;flex:none" role="img" aria-label="${esc(o.title || 'Donut chart')}">${segs}${centre}</svg>
      <div class="dl">${rows.map((d, i) => {
        const color = d.color || TONE[d.tone] || PALETTE[i % PALETTE.length];
        const pct = total > 0 ? Math.round((Number(d.value) / total) * 100) : 0;
        return `<div><span><i style="background:${color};width:9px;height:9px;border-radius:3px;display:inline-block;margin-right:.4rem"></i>${esc(d.label)}</span><b>${esc(num(d.value))} <span class="muted small">${pct}%</span></b></div>`;
      }).join('')}</div>
    </div>`;
  }

  /** Mini sparkline for metric cards. */
  function spark(values, color) {
    const v = (values || []).map((x) => Number(x) || 0);
    if (v.length < 2) return '';
    const W = 120, H = 42, max = Math.max.apply(null, [1].concat(v));
    const pts = v.map((x, i) => (i * (W - 4)) / (v.length - 1) + 2).map((x, i) => x.toFixed(1) + ' ' + (H - 3 - (v[i] / max) * (H - 8)).toFixed(1));
    const d = 'M' + pts.join(' L');
    const gid = nextId();
    return `<svg class="spark" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" aria-hidden="true">
      <defs><linearGradient id="${gid}" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="${color || '#c4736f'}" stop-opacity=".38"/><stop offset="100%" stop-color="${color || '#c4736f'}" stop-opacity="0"/>
      </linearGradient></defs>
      <path d="${d} L${pts[pts.length - 1].split(' ')[0]} ${H} L${pts[0].split(' ')[0]} ${H} Z" fill="url(#${gid})"/>
      <path d="${d}" fill="none" stroke="${color || '#c4736f'}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>`;
  }

  /** Horizontal bar list (HTML, not SVG) — data: [{ label, value, tone }] */
  function bars(data, o) {
    o = o || {};
    const rows = (data || []).filter((d) => d);
    if (!rows.length) return empty(o.empty || 'Nothing recorded yet.');
    const max = Math.max.apply(null, rows.map((d) => Number(d.value) || 0).concat([1]));
    const fmt = o.format || num;
    return rows.map((d) => {
      const color = d.color || TONE[d.tone] || 'var(--grad)';
      const w = ((Number(d.value) || 0) / max) * 100;
      return `<div class="hbar"><div class="nm" title="${esc(d.label)}">${esc(d.label)}</div>
        <div class="tr"><i style="width:${w.toFixed(1)}%;background:${color}"></i></div>
        <div class="vl">${esc(fmt(d.value))}</div></div>`;
    }).join('');
  }

  window.IHMS = window.IHMS || {};
  window.IHMS.charts = { line, column, donut, spark, bars, num, TONE, PALETTE, esc };
})();
