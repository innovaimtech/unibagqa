"""Informe mensual de Operaciones (PPTX) con el formato nuevo.

Abre data/plantilla_informe_mensual.pptx, rellena las tablas y gráficos nativos con los datos que entrega
MonthlyPresentationService (JSON) y deja solo las diapositivas que el sistema calcula.

Las diapositivas se ubican por su título, no por número: se pueden mover dentro de la plantilla.
Si falta alguna, el script termina con error (código 2) para no entregar un informe a medias.

Uso: python generate_monthly_pptx.py --template=... --month=2026-10 --data=datos.json --output=salida.pptx
"""
import argparse
import copy
import datetime
import json
import os
import sys
import unicodedata

from pptx import Presentation
from pptx.chart.data import CategoryChartData
from pptx.dml.color import RGBColor
from pptx.enum.chart import XL_CHART_TYPE, XL_LEGEND_POSITION, XL_LABEL_POSITION
from pptx.util import Emu, Pt

MESES_ABR = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sept', 'oct', 'nov', 'dic']
MESES_NOM = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE',
             'NOVIEMBRE', 'DICIEMBRE']
DIAS_ABR = ['lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom']

TEAL = RGBColor(0x00, 0x7A, 0x77)
GRAY = RGBColor(0xBF, 0xBF, 0xBF)
ORANGE = RGBColor(0xF7, 0x96, 0x46)
TEXT = RGBColor(0x1F, 0x1F, 0x1F)
AXIS = RGBColor(0x5A, 0x5A, 0x5A)

# Diapositivas que quedan, en orden: clave -> forma de reconocerla (diseño o comienzo del título).
KEEP = [
    ('portada', 'layout', 'PORTADA'),
    ('produccion', 'title', 'PRODUCCION – RESUMEN GLOBAL'),
    ('proyeccion', 'title', 'PRODUCCION – PROYECCION DE CIERRE'),
    ('sl_unidades', 'title', 'NIVEL DE SERVICIO [UNIDADES]'),
    ('sl_cc', 'title', 'NIVEL DE SERVICIO [C.C.]'),
    ('atrasados', 'title', 'NIVEL DE SERVICIO [C.C.] – ATRASADOS'),
    ('merma_flexo', 'title', 'MERMAS PRODUCCION – FLEXOGRAFIA'),
    ('merma_seri', 'title', 'MERMAS PRODUCCION – SERIGRAFIA'),
    ('merma_cys', 'title', 'MERMAS PRODUCCION – CORTE Y SELLADO'),
    ('merma_emb', 'title', 'MERMAS PRODUCCION – EMBALAJE'),
    ('merma_total', 'title', 'MERMAS PRODUCCION – TOTAL DE MERMA'),
    ('div_mant', 'title', 'MANTENCION.'),
    ('mant_flexo', 'title', 'MANTENCION – DISPONIBILIDAD: FLEXO'),
    ('mant_seri', 'title', 'MANTENCION – DISPONIBILIDAD: SERI'),
    ('mant_sellado', 'title', 'MANTENCION – DISPONIBILIDAD: SELLADO'),
    ('div_bodega', 'title', 'BODEGA DE MATERIAS PRIMAS Y PRODUCTO TERMINADO'),
    ('bod_clientes', 'title', 'BODEGA – STOCK CONSOLIDADO POR CLIENTE'),
    ('bod_rotacion', 'title', 'BODEGA – ROTACION DE STOCK'),
    ('bod_sin_rotacion', 'title', 'BODEGA – ARTICULOS SIN ROTACION'),
    ('bod_bobinas', 'title', 'BODEGA – BOBINAS PLANTA'),
    ('bod_pla', 'title', 'BODEGA – BOBINAS PLA'),
    ('cierre', 'layout', 'CIERRE'),
]


# ---------------------------------------------------------------- formato de números (Chile)

def fmt_int(v):
    return f"{int(round(float(v or 0))):,}".replace(',', '.')


def fmt_pct(v, d=2):
    return f"{float(v or 0):.{d}f}%".replace('.', ',')


def fmt_dec(v, d=1):
    return f"{float(v or 0):,.{d}f}".replace(',', 'X').replace('.', ',').replace('X', '.')


def fmt_kg(v):
    v = float(v or 0)
    return fmt_dec(v, 1) if 0 < abs(v) < 10 else fmt_int(v)


def month_label(month_key):
    y, m = month_key.split('-')
    return f"{MESES_ABR[int(m) - 1]}-{y[2:]}"


def month_name(month_key):
    y, m = month_key.split('-')
    return f"{MESES_NOM[int(m) - 1]} {y}"


def norm(text):
    """Mayúsculas sin tildes ni guiones, para comparar títulos."""
    text = unicodedata.normalize('NFKD', text or '').encode('ascii', 'ignore').decode('ascii')
    return ' '.join(text.upper().replace('-', ' ').split())


def mean(values):
    vals = [float(v) for v in values if v is not None]
    return sum(vals) / len(vals) if vals else 0.0


# ---------------------------------------------------------------- ayudantes de PowerPoint

def slide_title(slide):
    for sh in slide.shapes:
        if sh.is_placeholder and sh.has_text_frame and sh.text_frame.text.strip():
            return sh.text_frame.text.strip()
    return ''


VALID_DASHES = {'solid', 'dot', 'dash', 'lgDash', 'dashDot', 'lgDashDot', 'lgDashDotDot', 'sysDash', 'sysDot',
                'sysDashDot', 'sysDashDotDot'}


def repair_template(prs):
    """Corrige valores inválidos que traía la plantilla y que impiden abrirla en PowerPoint.

    Los gráficos de bobinas de la plantilla de septiembre tenían <a:prstDash val="solid,solid,sysDot"/>:
    se deja línea continua (la tendencia se vuelve a punteada al rellenar el gráfico).
    """
    fixed = 0
    for slide in prs.slides:
        for sh in slide.shapes:
            if not (getattr(sh, 'has_chart', False) and sh.has_chart):
                continue
            for el in sh.chart._chartSpace.iter('{http://schemas.openxmlformats.org/drawingml/2006/main}prstDash'):
                val = el.get('val', '')
                if val not in VALID_DASHES:
                    parts = [p for p in val.split(',') if p in VALID_DASHES]
                    el.set('val', parts[0] if parts else 'solid')
                    fixed += 1
    return fixed


def find_slides(prs):
    found = {}
    for idx, slide in enumerate(prs.slides):
        title = norm(slide_title(slide))
        layout = slide.slide_layout.name.upper()
        for key, kind, value in KEEP:
            if key in found:
                continue
            if kind == 'layout' and layout == value:
                found[key] = idx
                break
            if kind == 'title':
                want = norm(value)
                # «[C.C.]» y «[C.C.] – ATRASADOS» comparten el comienzo: la más corta debe calzar exacto.
                if title == want or (title.startswith(want) and key not in ('sl_cc',)):
                    found[key] = idx
                    break
    return found


def shape_named(slide, name):
    for sh in slide.shapes:
        if sh.name == name:
            return sh
    return None


def shapes_of(slide, kind):
    out = []
    for sh in slide.shapes:
        if kind == 'table' and getattr(sh, 'has_table', False) and sh.has_table:
            out.append(sh)
        elif kind == 'chart' and getattr(sh, 'has_chart', False) and sh.has_chart:
            out.append(sh)
        elif kind == 'picture' and sh.shape_type == 13:
            out.append(sh)
    return out


def set_text(text_frame_owner, text):
    """Cambia el texto conservando el formato del primer trozo (fuente, tamaño, color)."""
    tf = text_frame_owner.text_frame
    paragraphs = tf.paragraphs
    first = paragraphs[0]
    runs = first.runs
    if runs:
        runs[0].text = str(text)
        for r in runs[1:]:
            r._r.getparent().remove(r._r)
    else:
        first.text = str(text)
    for p in paragraphs[1:]:
        p._p.getparent().remove(p._p)


def set_cell(table, r, c, text):
    set_text(table.cell(r, c), text)


def text_shape(slide, startswith):
    """Primer cuadro de texto (no título) cuyo texto empieza con lo indicado."""
    for sh in slide.shapes:
        if sh.has_text_frame and not sh.is_placeholder and sh.text_frame.text.strip().startswith(startswith):
            return sh
    return None


def remove_table_rows(table, keep):
    rows = table._tbl.tr_lst
    for tr in rows[keep:]:
        table._tbl.remove(tr)


def clone_row(table, src_idx, dest_idx):
    tr = copy.deepcopy(table._tbl.tr_lst[src_idx])
    table._tbl.tr_lst[dest_idx].addprevious(tr) if dest_idx < len(table._tbl.tr_lst) else table._tbl.append(tr)


def replace_chart(chart, categories, series):
    data = CategoryChartData()
    data.categories = categories
    for name, values in series:
        data.add_series(name, [None if v is None else float(v) for v in values])
    chart.replace_data(data)
    # La plantilla trae escalas fijas (p. ej. 70–100 %): con datos nuevos el eje debe ser automático.
    try:
        axis = chart.value_axis
        axis.minimum_scale = None
        axis.maximum_scale = None
        axis.major_unit = None
    except Exception:
        pass


def style_new_chart(chart, colors, title, number_format, legend=True, font_size=9):
    chart.has_title = True
    chart.chart_title.text_frame.text = title
    tp = chart.chart_title.text_frame.paragraphs[0]
    for run in tp.runs:
        run.font.size = Pt(12)
        run.font.bold = False
        run.font.color.rgb = TEXT
    chart.font.size = Pt(font_size)
    chart.font.color.rgb = AXIS
    chart.font.name = 'Calibri'
    chart.has_legend = legend
    if legend:
        chart.legend.position = XL_LEGEND_POSITION.BOTTOM
        chart.legend.include_in_layout = False
    plot = chart.plots[0]
    for i, ser in enumerate(plot.series):
        color = colors[i % len(colors)]
        if chart.chart_type in (XL_CHART_TYPE.LINE_MARKERS, XL_CHART_TYPE.LINE):
            ser.format.line.color.rgb = color
            ser.format.line.width = Pt(2.25)
            ser.smooth = False
            try:
                ser.marker.format.fill.solid()
                ser.marker.format.fill.fore_color.rgb = color
                ser.marker.format.line.color.rgb = color
            except Exception:
                pass
        else:
            ser.format.fill.solid()
            ser.format.fill.fore_color.rgb = color
    plot.has_data_labels = True
    labels = plot.data_labels
    labels.number_format = number_format
    labels.number_format_is_linked = False
    labels.font.size = Pt(7.5)
    labels.font.color.rgb = TEXT
    if chart.chart_type in (XL_CHART_TYPE.LINE_MARKERS, XL_CHART_TYPE.LINE):
        labels.position = XL_LABEL_POSITION.ABOVE
    else:
        labels.position = XL_LABEL_POSITION.OUTSIDE_END
        plot.gap_width = 60
        plot.overlap = -10
    va = chart.value_axis
    va.has_major_gridlines = True
    va.major_gridlines.format.line.color.rgb = RGBColor(0xE3, 0xE3, 0xE3)
    va.format.line.fill.background()
    va.tick_labels.font.size = Pt(8)
    va.tick_labels.number_format = number_format
    va.tick_labels.number_format_is_linked = False
    ca = chart.category_axis
    ca.tick_labels.font.size = Pt(8)
    ca.format.line.color.rgb = GRAY


# ---------------------------------------------------------------- diapositivas

class Builder:
    def __init__(self, prs, data, month_key):
        self.prs = prs
        self.data = data
        self.month_key = month_key
        self.hist = data['history']
        # Con rango de fechas, la última columna lleva la etiqueta del rango («01/10–31/10») en vez del mes.
        self.labels = [h.get('label') or month_label(h['month_key']) for h in self.hist]
        self.col = data.get('column_label') or month_label(month_key)
        self.period = (data.get('period_title') or '').upper() or month_name(self.month_key)
        self.errors = []

    def need(self, cond, what):
        if not cond:
            self.errors.append(what)
        return cond

    # Tabla «DETALLE | U/M | mes | 2026 prom | 2026 total | 2025 prom | 2025 total»: se tocan columnas 2 a 4.
    def gestion_table(self, slide, rows):
        tables = shapes_of(slide, 'table')
        if not self.need(tables, f"tabla de gestión en «{slide_title(slide)}»"):
            return
        t = tables[0].table
        set_cell(t, 0, 2, self.col)
        for i, vals in enumerate(rows):
            if vals is None:
                continue
            for j, v in enumerate(vals):
                if v is not None:
                    set_cell(t, 2 + i, 2 + j, v)

    def encabezado(self):
        """El título «REPORTE DE OPERACIONES <MES>» está en el diseño CONTENIDO, no en cada diapositiva."""
        done = False
        for master in self.prs.slide_masters:
            for layout in master.slide_layouts:
                for sh in layout.shapes:
                    if sh.has_text_frame and sh.text_frame.text.strip().upper().startswith('REPORTE DE OPERACIONES'):
                        set_text(sh, f"REPORTE DE OPERACIONES {self.period}")
                        done = True
        self.need(done, 'diseño CONTENIDO: encabezado «REPORTE DE OPERACIONES»')

    def portada(self, slide):
        sh = shape_named(slide, 'Periodo') or text_shape(slide, MESES_NOM[8])
        if self.need(sh, 'portada: cuadro del período'):
            set_text(sh, self.period)

    def cierre(self, slide):
        sh = shape_named(slide, 'Fecha de emisión') or text_shape(slide, 'Reporte de Operaciones')
        if self.need(sh, 'cierre: fecha de emisión'):
            set_text(sh, f"Reporte de Operaciones · {(self.data.get('period_title') or month_name(self.month_key).capitalize())} · "
                         f"Fecha de emisión: {self.data.get('issued_at', '')}")

    def produccion(self, slide):
        prod = [float(h['prod_units']) for h in self.hist]
        cap = [float(h.get('prod_cap') or 0) for h in self.hist]
        meta = float(self.data.get('prod_meta') or 1000000)
        n = len(prod)
        self.gestion_table(slide, [
            [fmt_int(meta), None, None],
            [fmt_pct(prod[-1] / meta * 100), fmt_pct(mean(prod) / meta * 100), fmt_pct(sum(prod) / (meta * 12) * 100)],
            [fmt_int(prod[-1]), fmt_int(mean(prod)), fmt_int(sum(prod))],
        ])
        charts = shapes_of(slide, 'chart')
        if self.need(charts, 'producción: gráfico'):
            replace_chart(charts[0].chart, self.labels + ['Prom'], [
                ('Producción del mes', prod + [round(mean(prod))]),
                ('Capacidad productiva de las máquinas', cap + [round(mean(cap))]),
            ])

    def proyeccion(self, slide):
        p = self.data.get('projection') or {}
        made, queue, projected = float(p.get('made') or 0), float(p.get('queue') or 0), float(p.get('projected') or 0)
        # Mes EN CURSO (del 1 a hoy), aunque el informe sea de un mes cerrado.
        start = str(p.get('start') or self.data.get('start_date', ''))[:10]
        end = str(p.get('end') or self.data.get('end_date', ''))[:10]
        dmy = lambda s: f"{s[8:10]}/{s[5:7]}/{s[0:4]}" if len(s) == 10 else s
        tables = shapes_of(slide, 'table')
        if self.need(tables, 'proyección: tabla'):
            t = tables[0].table
            rate = f"{fmt_int(p.get('daily_avg') or 0)} unid./día hábil" if p.get('elapsed_days') else 'sin días hábiles completos aún'
            obs = [f"Unidades fabricadas (Corte y Sellado) del {dmy(start)} al {p.get('today', '')}.",
                   f"Faltan al mismo ritmo ({rate}, {p.get('remaining_days', 0)} de {p.get('total_days', 0)} días hábiles por cumplir).",
                   f"Cierre proyectado al {dmy(end)} si se mantiene el ritmo."]
            for i, (val, o) in enumerate(zip([made, queue, projected], obs)):
                set_cell(t, 1 + i, 1, fmt_int(val))
                set_cell(t, 1 + i, 2, o)
        charts = shapes_of(slide, 'chart')
        if self.need(charts, 'proyección: gráfico'):
            replace_chart(charts[0].chart, ['Bolsas fabricadas', 'En cola de fabricación'],
                          [('Proyección de cierre', [made, queue])])
        for sh in slide.shapes:
            if not sh.has_text_frame or sh.is_placeholder:
                continue
            txt = sh.text_frame.text.strip()
            if sh.name == 'Text 2':
                set_text(sh, fmt_int(projected))
            elif sh.name == 'Text 3' and txt.startswith('unidades'):
                set_text(sh, f"unidades de cierre proyectado al {dmy(end)}")
            elif sh.name == 'Text 5':
                set_text(sh, fmt_pct(made / projected * 100 if projected > 0 else 0, 0))
            elif sh.name == 'Text 6':
                set_text(sh, f"ya fabricado ({fmt_int(made)} unidades)")

    def sl_unidades(self, slide):
        tot = [float(h['sl_total_units']) for h in self.hist]
        dl = [float(h['sl_delayed_units']) for h in self.hist]
        pct = [((t - d) / t * 100) if t > 0 else 0 for t, d in zip(tot, dl)]
        tot_pct = (sum(tot) - sum(dl)) / sum(tot) * 100 if sum(tot) > 0 else 0
        self.gestion_table(slide, [
            [fmt_int(tot[-1]), fmt_int(mean(tot)), fmt_int(sum(tot))],
            [fmt_int(dl[-1]), fmt_int(mean(dl)), fmt_int(sum(dl))],
            [fmt_pct(pct[-1]), fmt_pct(mean(pct)), fmt_pct(tot_pct)],
        ])
        charts = shapes_of(slide, 'chart')
        if self.need(len(charts) >= 2, 'nivel de servicio [unidades]: 2 gráficos'):
            replace_chart(charts[0].chart, self.labels + ['Prom'], [
                ('Total de unidades pedidas', tot + [round(mean(tot))]),
                ('Unidades de pedidos atrasados', dl + [round(mean(dl))]),
            ])
            replace_chart(charts[1].chart, self.labels + ['Prom'], [
                ('Nivel de servicio (%)', [round(v, 2) for v in pct] + [round(mean(pct), 2)]),
            ])

    def sl_cc(self, slide):
        tot = [int(h['sl_total_cc']) for h in self.hist]
        dl = [int(h['sl_delayed_cc']) for h in self.hist]
        pct = [((t - d) / t * 100) if t > 0 else 0 for t, d in zip(tot, dl)]
        tot_pct = (sum(tot) - sum(dl)) / sum(tot) * 100 if sum(tot) > 0 else 0
        self.gestion_table(slide, [
            [str(tot[-1]), fmt_int(mean(tot)), fmt_int(sum(tot))],
            [str(dl[-1]), fmt_int(mean(dl)), fmt_int(sum(dl))],
            [fmt_pct(pct[-1]), fmt_pct(mean(pct)), fmt_pct(tot_pct)],
        ])
        charts = shapes_of(slide, 'chart')
        if self.need(len(charts) >= 2, 'nivel de servicio [C.C.]: 2 gráficos'):
            replace_chart(charts[0].chart, self.labels + ['Prom'], [
                ('Total de pedidos (CC/mes)', tot + [round(mean(tot))]),
                ('Pedidos atrasados (CC/mes)', dl + [round(mean(dl))]),
            ])
            replace_chart(charts[1].chart, self.labels + ['Prom'], [
                ('Nivel de servicio (%)', [round(v, 2) for v in pct] + [round(mean(pct), 2)]),
            ])

    def atrasados(self, slide):
        tables = shapes_of(slide, 'table')
        if not self.need(tables, 'atrasados: tabla'):
            return
        t = tables[0].table
        orders = list(self.data.get('delayed_orders') or [])[:10]
        n_rows = len(t._tbl.tr_lst)  # encabezado + 10 filas + total
        data_rows = n_rows - 2
        total_tr = t._tbl.tr_lst[-1]
        # Ajustar la cantidad de filas a los pedidos (mínimo una fila para el aviso).
        want = max(len(orders), 1)
        while data_rows > want:
            t._tbl.remove(t._tbl.tr_lst[data_rows])
            data_rows -= 1
        while data_rows < want:
            total_tr.addprevious(copy.deepcopy(t._tbl.tr_lst[1]))
            data_rows += 1
        if not orders:
            set_cell(t, 1, 0, '—')
            for c in range(1, 10):
                set_cell(t, 1, c, '')
            set_cell(t, 1, 9, 'Sin pedidos atrasados en el período.')
        total = 0.0
        for i, o in enumerate(orders):
            r = 1 + i
            date = str(o.get('required_date') or '')
            label = date
            try:
                d, m, y = [int(x) for x in date.split('/')]
                dt = datetime.date(y, m, d)
                label = f"{DIAS_ABR[dt.weekday()]} {d:02d}-{MESES_ABR[m - 1]}"
            except Exception:
                pass
            units = float(o.get('units') or 0)
            total += units
            values = [label, o.get('cc_number') or '—', str(o.get('customer_name') or '—')[:40], '—',
                      (str(o.get('product_type') or '')[:3] or '—'), '—', '—', str(-abs(int(o.get('delay_days') or 0))),
                      fmt_int(units), '']
            for c, v in enumerate(values):
                set_cell(t, r, c, v)
        set_cell(t, len(t._tbl.tr_lst) - 1, 8, fmt_int(total))
        note = shape_named(slide, 'Nota')
        if note is not None:
            set_text(note, 'Días al quiebre: días de atraso respecto de la fecha comprometida. '
                           'Fuente: guías de despacho del ERP (máximo 10 pedidos, los de mayor atraso).')

    def merma_proceso(self, slide, key):
        prog = [(h.get('procesos') or {}).get(key, {}).get('prog') for h in self.hist]
        proc = [(h.get('procesos') or {}).get(key, {}).get('proc') for h in self.hist]
        waste = [(h.get('procesos') or {}).get(key, {}).get('waste') for h in self.hist]
        pct = [(float(w) / float(p) * 100) if (w is not None and p) else None for w, p in zip(waste, prog)]
        cur = lambda s: float(s[-1] or 0)
        ok = [i for i in range(len(prog)) if prog[i] is not None]
        s_prog = sum(float(prog[i] or 0) for i in ok)
        s_proc = sum(float(proc[i] or 0) for i in ok)
        s_waste = sum(float(waste[i] or 0) for i in ok)
        self.gestion_table(slide, [
            [fmt_kg(cur(prog)), fmt_kg(mean(prog)), fmt_kg(s_prog)],
            [fmt_kg(cur(proc)), fmt_kg(mean(proc)), fmt_kg(s_proc)],
            [fmt_kg(cur(waste)), fmt_kg(mean(waste)), fmt_kg(s_waste)],
            [fmt_pct(pct[-1] or 0), fmt_pct(mean(pct)), fmt_pct(s_waste / s_prog * 100 if s_prog > 0 else 0)],
        ])
        calculated = [self.labels[i] for i, h in enumerate(self.hist) if h.get('origen') != 'plantilla']
        note = shape_named(slide, 'Nota')
        if note is not None and calculated:
            set_text(note, '% de merma del total = kg de merma ÷ kg programados. '
                           f"{calculated[0]} en adelante: kg calculados por el sistema (unidades × peso de la bolsa).")
        pics = shapes_of(slide, 'picture')
        if not self.need(pics, f"{slide_title(slide)}: imagen del gráfico"):
            return
        pic = pics[0]
        left, top = Emu(502920), pic.top
        width = Emu(8732520)
        height = pic.height
        pic._element.getparent().remove(pic._element)
        cats = self.labels + ['Prom']
        h_cols = int(height * 0.62)
        cd = CategoryChartData()
        cd.categories = cats
        cd.add_series('Kg programados', [float(v or 0) for v in prog] + [round(mean(prog))])
        cd.add_series('Kg procesados', [float(v or 0) for v in proc] + [round(mean(proc))])
        gf = slide.shapes.add_chart(XL_CHART_TYPE.COLUMN_CLUSTERED, left, top, width, Emu(h_cols), cd)
        style_new_chart(gf.chart, [TEAL, GRAY], 'Kg programados vs. procesados', '#,##0')
        cd2 = CategoryChartData()
        cd2.categories = cats
        cd2.add_series('% de merma', [round(v, 2) if v is not None else None for v in pct] + [round(mean(pct), 2)])
        gl = slide.shapes.add_chart(XL_CHART_TYPE.LINE_MARKERS, left, Emu(top + h_cols), width, Emu(height - h_cols), cd2)
        style_new_chart(gl.chart, [ORANGE], '% de merma (kg de merma ÷ kg programados)', '0.00"%"', legend=False)

    def merma_total(self, slide):
        real = [float(h.get('waste_pct') or 0) for h in self.hist]
        target = 1.6
        margin = [round(target - r, 2) for r in real]
        charts = shapes_of(slide, 'chart')
        if self.need(charts, 'total de merma: gráfico'):
            replace_chart(charts[0].chart, self.labels + ['Prom'], [
                ('% a cumplir (merma)', [target] * (len(real) + 1)),
                ('% real', [round(v, 2) for v in real] + [round(mean(real), 2)]),
                ('Margen (+/–)', margin + [round(target - mean(real), 2)]),
            ])

    def bod_clientes(self, slide):
        fg = self.data.get('finished_goods') or {}
        by = list((fg.get('by_customer') or {}).items())
        top = by[:13]
        rest = sum(v for _, v in by[13:])
        if rest > 0:
            top.append(('Varios', rest))
        charts = shapes_of(slide, 'chart')
        if self.need(charts, 'stock por cliente: gráfico') and top:
            chart = charts[0].chart
            replace_chart(chart, [k for k, _ in top], [(f"Stock al {self.data.get('stock_date', '')}", [v for _, v in top])])
            if chart.has_title:
                set_text(chart.chart_title, f"Stock consolidado por cliente (al {self.data.get('stock_date', '')})")

    def bod_rotacion(self, slide):
        fg = self.data.get('finished_goods') or {}
        total, moved, still = float(fg.get('total') or 0), float(fg.get('moved') or 0), float(fg.get('still') or 0)
        pm = moved / total * 100 if total > 0 else 0
        for sh in slide.shapes:
            if not sh.has_text_frame or sh.is_placeholder:
                continue
            if sh.name == 'Text 2':
                set_text(sh, fmt_int(moved))
            elif sh.name == 'Text 3':
                set_text(sh, f"stock con movimiento ({pm:.0f}%)")
            elif sh.name == 'Text 5':
                set_text(sh, fmt_int(still))
            elif sh.name == 'Text 6':
                set_text(sh, f"stock sin rotación ({100 - pm:.0f}%)" if total > 0 else 'stock sin rotación')
            elif sh.name == 'Nota':
                set_text(sh, f"Stock total (bodegas 400, 1000 y 700): {fmt_int(total)}. "
                             f"Sin rotación = sin salidas en los últimos 90 días.")
        charts = shapes_of(slide, 'chart')
        if self.need(charts, 'rotación: gráfico'):
            replace_chart(charts[0].chart, ['Stock con movimiento', 'Sin rotación'], [('Rotación de stock', [moved, still])])

    def bod_sin_rotacion(self, slide):
        fg = self.data.get('finished_goods') or {}
        still = float(fg.get('still') or 0)
        named = [(k, v) for k, v in (fg.get('still_by_customer') or {}).items() if k != 'Sin cliente']
        top_name, top_val = named[0] if named else ('—', 0)
        others = max(0.0, still - top_val)
        share = top_val / still * 100 if still > 0 else 0
        tables = shapes_of(slide, 'table')
        if self.need(tables, 'sin rotación: tabla'):
            t = tables[0].table
            set_cell(t, 0, 1, top_name)
            set_cell(t, 1, 0, fmt_int(still))
            set_cell(t, 1, 1, fmt_int(top_val))
            set_cell(t, 1, 2, fmt_int(others))
            set_cell(t, 2, 1, fmt_pct(share, 1))
            set_cell(t, 2, 2, fmt_pct(100 - share if still > 0 else 0, 1))
        for sh in slide.shapes:
            if not sh.has_text_frame or sh.is_placeholder:
                continue
            if sh.name == 'Text 2':
                set_text(sh, fmt_pct(share, 1))
            elif sh.name == 'Text 3':
                set_text(sh, f"del stock sin rotación corresponde a productos de {top_name} ({fmt_int(top_val)})")
        charts = shapes_of(slide, 'chart')
        if self.need(charts, 'sin rotación: gráfico'):
            replace_chart(charts[0].chart, [top_name, 'Otros'], [('Detalle sin rotación', [top_val, others])])

    def bobinas(self, slide, cols):
        rolls = self.data.get('rolls') or []
        tables = shapes_of(slide, 'table')
        labels = [month_label(r['month_key']) for r in rolls]
        series = [[float(r.get(k) or 0) for r in rolls] for k, _ in cols]
        if self.need(tables, f"{slide_title(slide)}: tabla"):
            t = tables[0].table
            for j, (_, name) in enumerate(cols):
                set_cell(t, 0, 1 + j, name)
            set_cell(t, 0, 3, 'Total')
            n_rows = len(t._tbl.tr_lst) - 1
            # Las filas de la tabla muestran los meses más recientes que quepan.
            show = list(range(len(rolls)))[-n_rows:]
            for r in range(n_rows):
                if r < len(show):
                    i = show[r]
                    a, b = series[0][i], series[1][i]
                    vals = [labels[i], fmt_int(a), fmt_int(b), fmt_int(a + b)]
                else:
                    vals = ['', '', '', '']
                for c, v in enumerate(vals):
                    set_cell(t, 1 + r, c, v)
        charts = shapes_of(slide, 'chart')
        if self.need(charts, f"{slide_title(slide)}: gráfico") and rolls:
            data = [(name, vals) for (_, name), vals in zip(cols, series)]
            if len(charts[0].chart.plots[0].series) >= 3:
                # Tercera serie: tendencia lineal de la segunda (mínimos cuadrados).
                y = series[1]
                n = len(y)
                xm = (n - 1) / 2
                ym = mean(y)
                den = sum((i - xm) ** 2 for i in range(n)) or 1
                slope = sum((i - xm) * (y[i] - ym) for i in range(n)) / den
                data.append((f"Tendencia lineal ({cols[1][1].lower()})", [round(ym + slope * (i - xm)) for i in range(n)]))
            chart = charts[0].chart
            replace_chart(chart, labels, data)
            from pptx.enum.dml import MSO_LINE
            for i, ser in enumerate(chart.plots[0].series):
                ser.format.line.dash_style = MSO_LINE.SQUARE_DOT if i >= 2 else MSO_LINE.SOLID


MANT_KEYS = ('div_mant', 'mant_flexo', 'mant_seri', 'mant_sellado')
ROMAN = {1: 'I', 2: 'II', 3: 'III', 4: 'IV', 5: 'V', 6: 'VI', 7: 'VII', 8: 'VIII', 9: 'IX', 10: 'X'}


def fmt_hm(seconds):
    """Segundos → «H:MM» (las horas pueden pasar de 24)."""
    v = float(seconds or 0)
    n = int(round(abs(v) / 60))
    return ('-' if v < 0 else '') + f"{n // 60}:{n % 60:02d}"


def machine_label(name):
    """«SELLADORA VI.» → «Selladora VI»."""
    parts = str(name).strip().rstrip('.').split()
    return ' '.join(p if p in ROMAN.values() else p.capitalize() for p in parts)


def set_lines(shape, lines):
    """Cambia el texto línea por línea (un párrafo por línea), conservando el formato de cada párrafo."""
    for i, para in enumerate(shape.text_frame.paragraphs):
        text = lines[i] if i < len(lines) else ''
        runs = para.runs
        if runs:
            runs[0].text = text
            for r in runs[1:]:
                r._r.getparent().remove(r._r)
        else:
            para.text = text


def mantencion(b, slide, group):
    """Disponibilidad de mantención de un proceso: tabla, base de cálculo y utilización por máquina."""
    m = b.data.get('maintenance') or {}
    rows = list((m.get('groups') or {}).get(group) or [])
    inactive = list((m.get('inactive') or {}).get(group) or [])
    tables = shapes_of(slide, 'table')
    if not b.need(tables, f"{slide_title(slide)}: tabla"):
        return
    t = tables[0].table
    headers = [norm(t.cell(0, c).text) for c in range(len(t.columns))]
    data_rows = len(t._tbl.tr_lst) - 1
    want = max(len(rows), 1)
    while data_rows > want:
        t._tbl.remove(t._tbl.tr_lst[-1])
        data_rows -= 1
    while data_rows < want:
        t._tbl.append(copy.deepcopy(t._tbl.tr_lst[1]))
        data_rows += 1
    closed = bool(b.data.get('month_closed', True))
    for i in range(want):
        r = rows[i] if i < len(rows) else None
        for c, h in enumerate(headers):
            if r is None:
                v = 'Sin máquinas con registros en el período' if c == 0 else ''
            elif h.startswith('MAQUINA'):
                v = machine_label(r['name'])
            elif h.startswith('HORAS TOTALES'):
                v = fmt_hm(r['total'])
            elif h.startswith('TIEMPO DISPONIBLE'):
                v = fmt_hm(r['available'])
            elif h.startswith('AVERIAS'):
                v = fmt_hm(r['breakdown'])
            elif h.startswith('ALISTAMIENTO'):
                v = fmt_hm(r['setup'])
            elif h.startswith('PAROS EXTERNOS'):
                v = fmt_hm(r['external'])
            elif h.startswith('TIEMPO OPERATIVO (BRUTO)'):
                v = fmt_hm(r['gross'])
            elif h.startswith('TIEMPO OPERATIVO (MANTENCION)'):
                v = fmt_hm(r['maintenance'])
            elif h.startswith('% DISPONIB'):
                v = '—' if r.get('real') is None else fmt_pct(r['real'])
            elif h.startswith('% ESPERADO'):
                v = fmt_pct(r['target'], 0)
            elif h.startswith('CUMPLE'):
                v = ('Cumple' if r.get('meets') else 'No cumple') if closed else 'En curso'
            else:
                v = ''
            set_cell(t, 1 + i, c, v)
    shown = {machine_label(r['name']) for r in rows}
    off_labels = [machine_label(n) for n in inactive]
    # Base de cálculo del mes.
    note = shape_named(slide, 'Nota')
    extra = shape_named(slide, 'Nota sin programación')
    if note is not None:
        mine = {}
        for h, ns in (m.get('preventive_hours') or {}).items():
            ns = [machine_label(n) for n in ns if machine_label(n) in shown]
            if ns:
                mine[h] = ns
        base_h = max(mine, key=lambda h: len(mine[h])) if mine else '8'
        text = (f"Base de cálculo del mes: {m.get('days', 0)} días disponibles · {fmt_hm(m.get('daily', 0))} h programadas por día · "
                f"colación de {fmt_hm(m.get('lunch', 0))} h · mantención preventiva programada de {fmt_hm(float(base_h) * 3600)} h.")
        for h, ns in sorted(mine.items()):
            if h != base_h:
                text += f" {', '.join(ns)}: mantención preventiva de {fmt_hm(float(h) * 3600)} h."
        text += ' Utilización = tiempo en producción ÷ tiempo disponible.'
        if off_labels and extra is None:
            text += ' Sin programación en el período: ' + ', '.join(off_labels) + '.'
        set_text(note, text)
        # La nota ocupa 2 líneas: si su cuadro es de una, sube al espacio libre bajo la tabla (sin tocar las imágenes).
        two_lines = 457200
        if note.height < two_lines:
            end = note.top + note.height
            table_bottom = tables[0].top + sum(int(tr.get('h') or 0) for tr in t._tbl.tr_lst)
            note.top = max(table_bottom + 91440, end - two_lines)
            note.height = end - note.top
    if extra is not None:
        set_lines(extra, ['Sin programación en el período: ' + ', '.join(off_labels) + '.' if off_labels else ''])
    # Utilización en los cuadros bajo las imágenes («FLEXO 2 / Utilización: 59%»).
    by_name = {norm(machine_label(r['name'])): r for r in rows}
    off = {norm(x) for x in off_labels}
    for sh in slide.shapes:
        if not (sh.has_text_frame and sh.name.startswith('Utilizaci')):
            continue
        first = sh.text_frame.paragraphs[0].text.strip()
        parts = first.split()
        if parts and parts[-1].isdigit():
            parts[-1] = ROMAN.get(int(parts[-1]), parts[-1])
        key = norm(' '.join(parts))
        if key in by_name:
            second = f"Utilización: {by_name[key]['utilization']:.0f}%"
        elif key in off:
            second = 'Sin programación'
        else:
            second = 'Sin registros'
        set_lines(sh, [first, second])


def main():
    ap = argparse.ArgumentParser(description='Informe mensual de Operaciones (PPTX)')
    ap.add_argument('--template', default=os.path.join('data', 'plantilla_informe_mensual.pptx'))
    ap.add_argument('--month', default='')
    ap.add_argument('--data', default='')
    ap.add_argument('--output', default=os.path.join('data', 'KPIS_OPERACIONES.pptx'))
    args = ap.parse_args()

    if not os.path.exists(args.template):
        print(f"Error: plantilla no encontrada: {args.template}")
        sys.exit(1)
    data = {}
    if args.data and os.path.exists(args.data):
        with open(args.data, 'r', encoding='utf-8') as f:
            data = json.load(f)
    month_key = args.month or str(data.get('month_key', ''))
    if not data.get('history') or not month_key:
        print('Error: faltan datos (history / month). Genere el informe desde el sistema.')
        sys.exit(1)

    prs = Presentation(args.template)
    repair_template(prs)
    found = find_slides(prs)
    # Si no se pudo calcular la mantención, sus 4 láminas no van; el resto del informe sí.
    with_mant = bool(data.get('maintenance'))
    keep = [k for k, _, _ in KEEP if with_mant or k not in MANT_KEYS]
    missing = [k for k in keep if k not in found]
    if missing:
        print('ERROR: la plantilla no tiene estas diapositivas: ' + ', '.join(missing))
        sys.exit(2)

    b = Builder(prs, data, month_key)
    s = lambda k: prs.slides[found[k]]
    b.encabezado()
    b.portada(s('portada'))
    b.produccion(s('produccion'))
    b.proyeccion(s('proyeccion'))
    b.sl_unidades(s('sl_unidades'))
    b.sl_cc(s('sl_cc'))
    b.atrasados(s('atrasados'))
    for key, proc in [('merma_flexo', 'flexo'), ('merma_seri', 'seri'), ('merma_cys', 'cys'), ('merma_emb', 'emb')]:
        b.merma_proceso(s(key), proc)
    b.merma_total(s('merma_total'))
    if with_mant:
        mantencion(b, s('mant_flexo'), 'flexo')
        mantencion(b, s('mant_seri'), 'seri')
        mantencion(b, s('mant_sellado'), 'sellado')
    b.bod_clientes(s('bod_clientes'))
    b.bod_rotacion(s('bod_rotacion'))
    b.bod_sin_rotacion(s('bod_sin_rotacion'))
    # Bobinas en bolsas proyectadas (cálculo del Libro1: largo ÷ metros por bolsa, promedio de 9 formatos).
    b.bobinas(s('bod_bobinas'), [('pp_bolsas', 'Stock PP'), ('pla_bolsas', 'Stock PLA')])
    b.bobinas(s('bod_pla'), [('pla_beige_bolsas', 'Beige PLA'), ('pla_blanco_bolsas', 'Blanco PLA')])
    b.cierre(s('cierre'))

    if b.errors:
        print('ERROR: faltan elementos en la plantilla; el informe quedaría desactualizado:')
        for e in b.errors:
            print('  - ' + e)
        sys.exit(2)

    # Quitar las diapositivas que no calcula el sistema y dejar el orden de KEEP.
    keep_idx = [found[k] for k in keep]
    sld_ids = list(prs.slides._sldIdLst)
    for i in range(len(sld_ids) - 1, -1, -1):
        if i not in keep_idx:
            prs.part.drop_rel(sld_ids[i].rId)
            prs.slides._sldIdLst.remove(sld_ids[i])
    ordered = [sld_ids[i] for i in keep_idx]
    for el in ordered:
        prs.slides._sldIdLst.remove(el)
    for el in ordered:
        prs.slides._sldIdLst.append(el)

    saved = args.output
    try:
        prs.save(saved)
    except PermissionError:
        base, ext = os.path.splitext(saved)
        saved = f"{base}_NUEVO{ext}"
        prs.save(saved)
    print(f"Presentacion generada: {saved} ({os.path.getsize(saved)} bytes, {len(prs.slides)} diapositivas)")


if __name__ == '__main__':
    main()
