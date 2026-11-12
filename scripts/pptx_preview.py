#!/usr/bin/env python3
"""
Vista previa en HTML de un PPTX (informe mensual), sin LibreOffice ni PowerPoint.

Lee el archivo ya generado con python-pptx y dibuja cada diapositiva con sus posiciones reales:
rectángulos, textos, imágenes, tablas y gráficos nativos (barras, líneas y donas, en SVG).
Es una vista aproximada (no usa el motor de PowerPoint): sirve para revisar contenido y cifras.

Uso: python pptx_preview.py archivo.pptx salida.html
"""
import base64
import hashlib
import html
import sys

from lxml import etree
from pptx import Presentation
from pptx.enum.shapes import MSO_SHAPE_TYPE
from pptx.opc.constants import RELATIONSHIP_TYPE as RT

NS = {'a': 'http://schemas.openxmlformats.org/drawingml/2006/main',
      'p': 'http://schemas.openxmlformats.org/presentationml/2006/main'}
PALETTE = ['1F4E79', 'ED7D31', 'A5A5A5', 'FFC000', '5B9BD5', '70AD47', '264478', '9E480E']


def esc(t):
    return html.escape(str(t if t is not None else ''), quote=True)


def num(v, dec=0):
    """Número con miles chilenos (1.234,5)."""
    try:
        v = float(v)
    except (TypeError, ValueError):
        return ''
    s = f"{v:,.{dec}f}"
    return s.replace(',', '§').replace('.', ',').replace('§', '.')


def shrink(blob, content_type, max_side=1100):
    """Achica imágenes grandes (la vista previa no necesita la resolución de impresión). Sin Pillow, queda igual."""
    try:
        import io
        from PIL import Image
        im = Image.open(io.BytesIO(blob))
        if max(im.size) <= max_side and len(blob) < 300_000:
            return blob, content_type
        im.thumbnail((max_side, max_side))
        out = io.BytesIO()
        if im.mode in ('RGBA', 'LA', 'P'):
            im.save(out, 'PNG', optimize=True)
            return out.getvalue(), 'image/png'
        im.convert('RGB').save(out, 'JPEG', quality=82)
        return out.getvalue(), 'image/jpeg'
    except Exception:
        return blob, content_type


class Renderer:
    def __init__(self, prs):
        self.prs = prs
        self.W = int(prs.slide_width)
        self.H = int(prs.slide_height)
        self.images = {}
        self.theme = self._theme_colors()

    # ------------------------------------------------------------- colores
    def _theme_colors(self):
        colors = {}
        try:
            theme = self.prs.slide_masters[0].part.part_related_by(RT.THEME)
            root = etree.fromstring(theme.blob)
            scheme = root.find('.//a:clrScheme', NS)
            for el in scheme:
                tag = etree.QName(el).localname
                c = el[0]
                colors[tag] = c.get('lastClr') or c.get('val')
        except Exception:
            pass
        alias = {'bg1': 'lt1', 'tx1': 'dk1', 'bg2': 'lt2', 'tx2': 'dk2'}
        for k, v in alias.items():
            if v in colors:
                colors[k] = colors[v]
        return colors

    def color(self, fmt):
        """Color de un ColorFormat (RGB o del tema) como 'RRGGBB', o None."""
        try:
            if fmt.type is None:
                return None
            if fmt.type == 1:  # RGB
                return str(fmt.rgb)
            name = str(fmt.theme_color).split(' ')[0].lower()
            key = {'accent_1': 'accent1', 'accent_2': 'accent2', 'accent_3': 'accent3', 'accent_4': 'accent4',
                   'accent_5': 'accent5', 'accent_6': 'accent6', 'background_1': 'bg1', 'background_2': 'bg2',
                   'text_1': 'tx1', 'text_2': 'tx2', 'dark_1': 'dk1', 'light_1': 'lt1', 'dark_2': 'dk2',
                   'light_2': 'lt2', 'hyperlink': 'hlink'}.get(name, name)
            return self.theme.get(key)
        except Exception:
            return None

    def fill(self, fill):
        try:
            if fill.type == 1:  # sólido
                return self.color(fill.fore_color)
        except Exception:
            pass
        return None

    def xml_fill(self, el):
        """Relleno sólido leído directo del XML (fondos), o None."""
        if el is None:
            return None
        s = el.find('.//a:solidFill', NS)
        if s is None or len(s) == 0:
            return None
        c = s[0]
        if etree.QName(c).localname == 'srgbClr':
            return c.get('val')
        if etree.QName(c).localname == 'schemeClr':
            return self.theme.get(c.get('val'))
        return None

    # ------------------------------------------------------------- utilidades
    def box(self, sh, extra=''):
        l, t = int(sh.left or 0), int(sh.top or 0)
        w, h = int(sh.width or 0), int(sh.height or 0)
        return (f"left:{l / self.W * 100:.3f}%;top:{t / self.H * 100:.3f}%;"
                f"width:{w / self.W * 100:.3f}%;height:{h / self.H * 100:.3f}%;{extra}")

    def cq(self, emu):
        """Medida en EMU → unidades del ancho de la diapositiva (cqw)."""
        return f"{emu / self.W * 100:.3f}cqw"

    def pt(self, size_pt):
        return self.cq(size_pt * 12700)

    def image_class(self, blob, content_type):
        key = 'im' + hashlib.sha1(blob).hexdigest()[:12]
        if key not in self.images:
            blob, content_type = shrink(blob, content_type)
            self.images[key] = f"data:{content_type};base64,{base64.b64encode(blob).decode('ascii')}"
        return key

    # ------------------------------------------------------------- texto
    def default_size(self, sh, layout):
        """Tamaño de letra heredado del placeholder del diseño (si el texto no lo trae)."""
        try:
            if sh.is_placeholder and layout is not None:
                idx = sh.placeholder_format.idx
                for lsh in layout.placeholders:
                    if lsh.placeholder_format.idx == idx:
                        for p in lsh.text_frame.paragraphs:
                            for r in p.runs:
                                if r.font.size:
                                    return r.font.size.pt
                        d = lsh._element.find('.//a:defRPr', NS)
                        if d is not None and d.get('sz'):
                            return int(d.get('sz')) / 100
        except Exception:
            pass
        return 14

    def text_html(self, tf, base_size=14, base_color=None):
        out = []
        anchor = 'flex-start'
        try:
            bp = tf._txBody.find('a:bodyPr', NS)
            a = bp.get('anchor') if bp is not None else None
            anchor = {'ctr': 'center', 'b': 'flex-end'}.get(a, 'flex-start')
        except Exception:
            pass
        for p in tf.paragraphs:
            align = {1: 'left', 2: 'center', 3: 'right', 4: 'justify'}.get(int(p.alignment) if p.alignment is not None else 1, 'left')
            psize = None
            try:
                pr = p._p.find('a:pPr/a:defRPr', NS)
                if pr is not None and pr.get('sz'):
                    psize = int(pr.get('sz')) / 100
            except Exception:
                pass
            runs = []
            for r in p.runs:
                f = r.font
                size = f.size.pt if f.size else (psize or base_size)
                col = None
                try:
                    col = self.color(f.color) if f.color and f.color.type is not None else None
                except Exception:
                    col = None
                style = f"font-size:{self.pt(size)};"
                if f.bold:
                    style += 'font-weight:700;'
                if f.italic:
                    style += 'font-style:italic;'
                if col or base_color:
                    style += f"color:#{col or base_color};"
                runs.append(f'<span style="{style}">{esc(r.text)}</span>')
            if not runs:
                runs.append(f'<span style="font-size:{self.pt(psize or base_size)}">&nbsp;</span>')
            out.append(f'<p style="text-align:{align}">{"".join(runs)}</p>')
        return anchor, ''.join(out)

    # ------------------------------------------------------------- formas
    def shape(self, sh, layout=None):
        try:
            if sh.is_placeholder and (sh.name.startswith('Slide Number') or sh.placeholder_format.type is not None
                                      and 'SLIDE_NUMBER' in str(sh.placeholder_format.type)):
                return ''
        except Exception:
            pass
        try:
            st = sh.shape_type
            if st == MSO_SHAPE_TYPE.GROUP:
                return ''.join(self.shape(c, layout) for c in sh.shapes)
            if getattr(sh, 'has_chart', False) and sh.has_chart:
                return f'<div class="o" style="{self.box(sh)}">{self.chart(sh)}</div>'
            if getattr(sh, 'has_table', False) and sh.has_table:
                return f'<div class="o" style="{self.box(sh)}">{self.table(sh)}</div>'
            if st == MSO_SHAPE_TYPE.PICTURE or (hasattr(sh, 'image') and not getattr(sh, 'has_text_frame', False)):
                return self.picture(sh)
            return self.auto(sh, layout)
        except Exception as e:  # una forma rara no debe romper la vista previa
            return f'<!-- {esc(e)} -->'

    def picture(self, sh):
        img = sh.image
        cls = self.image_class(img.blob, img.content_type)
        cl, cr, ct, cb = (float(sh.crop_left or 0), float(sh.crop_right or 0), float(sh.crop_top or 0), float(sh.crop_bottom or 0))
        w = 100 / max(0.01, 1 - cl - cr)
        h = 100 / max(0.01, 1 - ct - cb)
        return (f'<div class="o" style="{self.box(sh, "overflow:hidden")}"><div class="{cls} im" style="position:absolute;'
                f'left:{-cl * w:.3f}%;top:{-ct * h:.3f}%;width:{w:.3f}%;height:{h:.3f}%"></div></div>')

    def auto(self, sh, layout):
        style = ''
        bg = None
        try:
            bg = self.fill(sh.fill)
        except Exception:
            pass
        if bg:
            style += f'background:#{bg};'
        try:
            if sh.line.fill.type == 1:
                lc = self.color(sh.line.color)
                lw = max(1, int((sh.line.width or 12700) / 12700))
                if lc:
                    style += f'box-shadow:inset 0 0 0 {self.pt(lw * 0.75)} #{lc};'
        except Exception:
            pass
        try:
            ast = str(sh.auto_shape_type)
            if 'ROUNDED' in ast:
                style += 'border-radius:8%/' + ('14%' if (sh.height or 1) < (sh.width or 1) else '8%') + ';'
            elif 'OVAL' in ast:
                style += 'border-radius:50%;'
        except Exception:
            pass
        inner = ''
        if getattr(sh, 'has_text_frame', False) and sh.has_text_frame and sh.text_frame.text.strip():
            anchor, txt = self.text_html(sh.text_frame, self.default_size(sh, layout))
            inner = f'<div class="tx" style="justify-content:{anchor}">{txt}</div>'
        if not style and not inner:
            return ''
        return f'<div class="o" style="{self.box(sh, style)}">{inner}</div>'

    # ------------------------------------------------------------- tablas
    def table(self, sh):
        t = sh.table
        total_w = sum(int(c.width) for c in t.columns) or 1
        cols = ''.join(f'<col style="width:{int(c.width) / total_w * 100:.2f}%">' for c in t.columns)
        rows = []
        for r_i, row in enumerate(t.rows):
            cells = []
            for c_i, cell in enumerate(row.cells):
                if cell.is_spanned:
                    continue
                span = ''
                if cell.is_merge_origin:
                    span = f' colspan="{cell.span_width}" rowspan="{cell.span_height}"'
                bg = self.fill(cell.fill)
                anchor = {'ctr': 'middle', 'b': 'bottom'}.get(cell._tc.get('anchor') or (cell._tc.find('a:tcPr', NS).get('anchor') if cell._tc.find('a:tcPr', NS) is not None else ''), 'middle')
                _, txt = self.text_html(cell.text_frame, 10)
                cells.append(f'<td{span} style="vertical-align:{anchor};{f"background:#{bg};" if bg else ""}">{txt}</td>')
            rows.append(f'<tr style="height:{int(row.height) / int(sh.height or 1) * 100:.2f}%">{"".join(cells)}</tr>')
        return f'<table class="tb"><colgroup>{cols}</colgroup>{"".join(rows)}</table>'

    # ------------------------------------------------------------- gráficos
    def series_color(self, s, i, line=False):
        c = None
        try:
            c = self.color(s.format.line.color) if line else self.fill(s.format.fill)
        except Exception:
            c = None
        if not c:
            try:
                c = self.fill(s.format.fill) if line else self.color(s.format.line.color)
            except Exception:
                c = None
        return c or self.theme.get(f'accent{i % 6 + 1}') or PALETTE[i % len(PALETTE)]

    def chart(self, sh):
        ch = sh.chart
        Wv, Hv = 1000.0, 1000.0 * int(sh.height) / max(1, int(sh.width))
        # Letra pareja en todos los gráficos: el viewBox mide 1000 de ancho, así que un gráfico angosto la achica.
        fs = 15.0 * max(1.0, min(2.2, 8700000 / max(1, int(sh.width))))
        parts = []
        title = ''
        try:
            if ch.has_title and ch.chart_title.has_text_frame:
                title = ch.chart_title.text_frame.text
        except Exception:
            title = ''
        top = 12.0
        if title:
            parts.append(f'<text x="{Wv / 2}" y="{top + fs}" text-anchor="middle" font-size="{fs * 1.15}" font-weight="700" fill="#333">{esc(title)}</text>')
            top += fs * 2.2
        plots = list(ch.plots)
        legend = []
        donut = [p for p in plots if p.__class__.__name__ in ('DoughnutPlot', 'PiePlot')]
        if donut:
            p = donut[0]
            cats = [str(c) for c in p.categories]
            vals = [float(v or 0) for v in (list(p.series)[0].values if len(p.series) else [])]
            tot = sum(v for v in vals if v > 0) or 1
            cx, cy = Wv * 0.5, top + (Hv - top - fs * 3) / 2
            R = min(Wv * 0.32, (Hv - top - fs * 3) / 2 * 0.95)
            r0 = R * (0.55 if p.__class__.__name__ == 'DoughnutPlot' else 0)
            import math
            ang = -math.pi / 2
            pts = list(p.series)[0].points if len(p.series) else []
            for i, v in enumerate(vals):
                if v <= 0:
                    continue
                a2 = ang + 2 * math.pi * v / tot
                large = 1 if a2 - ang > math.pi else 0
                col = None
                try:
                    col = self.fill(pts[i].format.fill)
                except Exception:
                    col = None
                col = col or self.theme.get(f'accent{i % 6 + 1}') or PALETTE[i % len(PALETTE)]
                x1, y1 = cx + R * math.cos(ang), cy + R * math.sin(ang)
                x2, y2 = cx + R * math.cos(a2), cy + R * math.sin(a2)
                if v >= tot * 0.9999:
                    parts.append(f'<circle cx="{cx}" cy="{cy}" r="{(R + r0) / 2}" fill="none" stroke="#{col}" stroke-width="{R - r0}"/>')
                else:
                    d = f'M{x1:.1f},{y1:.1f} A{R},{R} 0 {large} 1 {x2:.1f},{y2:.1f} '
                    if r0 > 0:
                        x3, y3 = cx + r0 * math.cos(a2), cy + r0 * math.sin(a2)
                        x4, y4 = cx + r0 * math.cos(ang), cy + r0 * math.sin(ang)
                        d += f'L{x3:.1f},{y3:.1f} A{r0},{r0} 0 {large} 0 {x4:.1f},{y4:.1f} Z'
                    else:
                        d += f'L{cx},{cy} Z'
                    parts.append(f'<path d="{d}" fill="#{col}" stroke="#fff" stroke-width="2"/>')
                mid = (ang + a2) / 2
                lr = (R + r0) / 2 if r0 else R * 0.62
                parts.append(f'<text x="{cx + lr * math.cos(mid):.1f}" y="{cy + lr * math.sin(mid) + fs * 0.35:.1f}" text-anchor="middle" '
                             f'font-size="{fs}" font-weight="700" fill="#fff">{v / tot * 100:.0f}%</text>')
                legend.append((cats[i] if i < len(cats) else '', col, False))
                ang = a2
        else:
            bars = [p for p in plots if p.__class__.__name__ == 'BarPlot']
            lines = [p for p in plots if p.__class__.__name__ == 'LinePlot']
            cats = []
            for p in plots:
                try:
                    cats = [str(c) for c in p.categories]
                    if cats:
                        break
                except Exception:
                    pass
            n = max(1, len(cats))
            horizontal = 'BAR_' in str(ch.chart_type) and 'COLUMN' not in str(ch.chart_type)
            bar_series = [(s, i) for p in bars for i, s in enumerate(p.series)]
            line_series = [(s, i + len(bar_series)) for p in lines for i, s in enumerate(p.series)]
            vmax_b = max([float(v or 0) for s, _ in bar_series for v in s.values] + [0])
            vmax_l = max([float(v or 0) for s, _ in line_series for v in s.values] + [0])
            separate = vmax_b > 0 and vmax_l > 0 and (vmax_b / max(vmax_l, 1e-9) > 5 or vmax_l / max(vmax_b, 1e-9) > 5)
            scale_b = (vmax_b if separate else max(vmax_b, vmax_l)) * 1.12 or 1
            # Si el gráfico trae un máximo fijo en el eje (margen para las etiquetas), se respeta como en PowerPoint.
            try:
                fixed_max = ch.value_axis.maximum_scale
                if fixed_max and not separate and fixed_max >= max(vmax_b, vmax_l):
                    scale_b = scale_l = float(fixed_max)
            except Exception:
                pass
            # Barras horizontales: el ancho para los nombres depende del nombre más largo; los que no caben se acortan
            # con «…» (como PowerPoint) en vez de cortarse por la izquierda.
            label_fs = fs * 0.8
            char_w = label_fs * 0.55
            label_w = min(Wv * 0.42, max(Wv * 0.18, max([len(c) for c in cats] + [0]) * char_w + 12)) if horizontal else 0
            max_chars = max(4, int((label_w - 12) / char_w)) if horizontal else 0
            scale_l = (vmax_l * 1.12 or 1) if separate else scale_b
            leg_h = fs * 2.2 if ch.has_legend else 0
            x0, x1 = (label_w if horizontal else 70.0), Wv - (60.0 if separate else (fs * 3.2 if horizontal else 20.0))
            y0, y1 = top + 6, Hv - fs * 2.4 - leg_h
            parts.append(f'<line x1="{x0}" y1="{y1}" x2="{x1}" y2="{y1}" stroke="#bbb"/>')
            for k in range(1, 5):
                if horizontal:
                    gx = x0 + (x1 - x0) * k / 4
                    parts.append(f'<line x1="{gx}" y1="{y0}" x2="{gx}" y2="{y1}" stroke="#eee"/>')
                else:
                    gy = y1 - (y1 - y0) * k / 4
                    parts.append(f'<line x1="{x0}" y1="{gy}" x2="{x1}" y2="{gy}" stroke="#eee"/>'
                                 f'<text x="{x0 - 6}" y="{gy + fs * 0.35}" text-anchor="end" font-size="{fs * 0.85}" fill="#777">{num(scale_b * k / 4, 1 if scale_b < 10 else 0)}</text>')
            band = ((y1 - y0) if horizontal else (x1 - x0)) / n
            nb = max(1, len(bar_series))
            bw = band * 0.75 / nb
            labels_on = any(getattr(p, 'has_data_labels', False) for p in plots)
            for j, (s, i) in enumerate(bar_series):
                col = self.series_color(s, i)
                legend.append((s.name, col, False))
                for k, v in enumerate(s.values):
                    v = float(v or 0)
                    if horizontal:
                        y = y0 + band * k + band * 0.125 + bw * j
                        w = (x1 - x0) * v / scale_b
                        parts.append(f'<rect x="{x0}" y="{y:.1f}" width="{max(0, w):.1f}" height="{bw:.1f}" fill="#{col}"/>')
                        if labels_on or nb == 1:
                            parts.append(f'<text x="{x0 + w + 5:.1f}" y="{y + bw / 2 + fs * 0.35:.1f}" font-size="{fs * 0.8}" fill="#333">{num(v)}</text>')
                    else:
                        x = x0 + band * k + band * 0.125 + bw * j
                        hh = (y1 - y0) * v / scale_b
                        parts.append(f'<rect x="{x:.1f}" y="{y1 - hh:.1f}" width="{bw:.1f}" height="{max(0, hh):.1f}" fill="#{col}"/>')
                        if labels_on:
                            parts.append(f'<text x="{x + bw / 2:.1f}" y="{y1 - hh - 4:.1f}" text-anchor="middle" font-size="{fs * 0.7}" fill="#333">{num(v)}</text>')
            for s, i in line_series:
                col = self.series_color(s, i, line=True)
                legend.append((s.name, col, True))
                pts = []
                for k, v in enumerate(s.values):
                    if v is None:
                        continue
                    x = x0 + band * k + band / 2
                    y = y1 - (y1 - y0) * float(v) / scale_l
                    pts.append((x, y, float(v)))
                if pts:
                    parts.append(f'<polyline points="{" ".join(f"{x:.1f},{y:.1f}" for x, y, _ in pts)}" fill="none" stroke="#{col}" stroke-width="3"/>')
                    for x, y, v in pts:
                        parts.append(f'<circle cx="{x:.1f}" cy="{y:.1f}" r="4" fill="#{col}"/>')
                        if labels_on or separate:
                            parts.append(f'<text x="{x:.1f}" y="{y - 8:.1f}" text-anchor="middle" font-size="{fs * 0.7}" fill="#{col}">{num(v, 1 if v < 100 else 0)}</text>')
            for k, c in enumerate(cats):
                if horizontal:
                    shown = c if len(c) <= max_chars else c[:max_chars - 1].rstrip() + '…'
                    parts.append(f'<text x="{x0 - 6}" y="{y0 + band * k + band / 2 + fs * 0.35:.1f}" text-anchor="end" font-size="{label_fs}" fill="#444">{esc(shown)}</text>')
                else:
                    parts.append(f'<text x="{x0 + band * k + band / 2:.1f}" y="{y1 + fs * 1.2}" text-anchor="middle" font-size="{fs * (0.8 if n <= 14 else 0.6)}" fill="#444">{esc(c[:14])}</text>')
        if legend and (ch.has_legend or donut):
            total = sum(len(str(t)) * fs * 0.55 + fs * 2.6 for t, _, _ in legend)
            x = max(10.0, (Wv - total) / 2)
            y = Hv - fs * 0.8
            for t, col, is_line in legend:
                mark = (f'<line x1="{x}" y1="{y - fs * 0.35}" x2="{x + fs * 1.4}" y2="{y - fs * 0.35}" stroke="#{col}" stroke-width="3"/>' if is_line
                        else f'<rect x="{x}" y="{y - fs * 0.8}" width="{fs * 0.9}" height="{fs * 0.9}" fill="#{col}"/>')
                parts.append(mark + f'<text x="{x + fs * 1.7}" y="{y}" font-size="{fs * 0.85}" fill="#444">{esc(t)}</text>')
                x += len(str(t)) * fs * 0.55 + fs * 2.6
        return f'<svg viewBox="0 0 {Wv:.0f} {Hv:.0f}" preserveAspectRatio="none" style="width:100%;height:100%;font-family:Calibri,Arial,sans-serif">{"".join(parts)}</svg>'

    # ------------------------------------------------------------- diapositivas
    def background(self, slide):
        for el in (slide._element, slide.slide_layout._element, slide.slide_layout.slide_master._element):
            c = self.xml_fill(el.find('.//p:cSld/p:bg', NS))
            if c:
                return c
        return 'FFFFFF'

    def slide(self, slide):
        layout = slide.slide_layout
        body = []
        for sh in layout.shapes:
            if sh.is_placeholder:
                continue
            body.append(self.shape(sh, layout))
        for sh in slide.shapes:
            body.append(self.shape(sh, layout))
        return f'<div class="sl" style="background:#{self.background(slide)}">{"".join(body)}</div>'

    def document(self):
        slides = [self.slide(s) for s in self.prs.slides]
        css = ''.join(f'.{k}{{background-image:url({v})}}' for k, v in self.images.items())
        sections = ''.join(f'<section class="pg" id="s{i + 1}" data-n="{i + 1}">{s}</section>' for i, s in enumerate(slides))
        return f"""<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vista previa</title><style>
html,body{{margin:0;background:#334155;font-family:Calibri,Arial,sans-serif;color:#111}}
.bar{{position:sticky;top:0;z-index:5;display:flex;align-items:center;justify-content:center;gap:10px;padding:8px;background:#1e293b;color:#fff;font:600 14px Arial,sans-serif}}
.bar button{{min-width:40px;height:34px;border:0;border-radius:8px;background:#475569;color:#fff;font-size:15px;cursor:pointer}}
.bar button:disabled{{opacity:.35;cursor:default}}
.wrap{{padding:14px;display:flex;justify-content:center}}
.pg{{width:min(100%,calc((100vh - 90px) * {self.W / self.H:.4f}));display:none}}
.pg.on{{display:block}}
.sl{{position:relative;width:100%;aspect-ratio:{self.W}/{self.H};overflow:hidden;container-type:inline-size;box-shadow:0 6px 24px rgba(0,0,0,.35)}}
.o{{position:absolute;box-sizing:border-box}}
.im{{background-size:100% 100%;background-repeat:no-repeat}}
.tx{{position:absolute;inset:0;display:flex;flex-direction:column;padding:0.6cqw 1cqw;overflow:hidden;line-height:1.15}}
.tx p,.tb p{{margin:0}}
.tb{{width:100%;height:100%;border-collapse:collapse;table-layout:fixed}}
.tb td{{border:0.1cqw solid #d0d5dd;padding:0.25cqw 0.5cqw;overflow:hidden;line-height:1.1}}
{css}
</style></head><body>
<div class="bar"><button type="button" id="pv">◀</button><span id="lbl"></span><button type="button" id="nx">▶</button></div>
<div class="wrap">{sections}</div>
<script>(function(){{var pgs=[].slice.call(document.querySelectorAll('.pg')),i=0;
function show(n){{i=Math.max(0,Math.min(pgs.length-1,n));pgs.forEach(function(p,k){{p.classList.toggle('on',k===i);}});
document.getElementById('lbl').textContent='Lámina '+(i+1)+' de '+pgs.length;document.getElementById('pv').disabled=i===0;document.getElementById('nx').disabled=i===pgs.length-1;
try{{history.replaceState(null,'','#s'+(i+1));}}catch(e){{}}try{{parent.postMessage({{pptSlide:i+1}},'*');}}catch(e){{}}}}
document.getElementById('pv').onclick=function(){{show(i-1);}};document.getElementById('nx').onclick=function(){{show(i+1);}};
document.addEventListener('keydown',function(e){{if(e.key==='ArrowLeft')show(i-1);if(e.key==='ArrowRight')show(i+1);}});
window.addEventListener('hashchange',function(){{var m=/s(\\d+)/.exec(location.hash);if(m)show(+m[1]-1);}});
var m=/s(\\d+)/.exec(location.hash);show(m?+m[1]-1:0);}})();</script>
</body></html>"""


def main():
    if len(sys.argv) < 3:
        print('Uso: python pptx_preview.py archivo.pptx salida.html')
        sys.exit(1)
    prs = Presentation(sys.argv[1])
    out = Renderer(prs).document()
    with open(sys.argv[2], 'w', encoding='utf-8') as f:
        f.write(out)


if __name__ == '__main__':
    main()
