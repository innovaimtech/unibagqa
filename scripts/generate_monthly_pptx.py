import os
import sys
import json
import argparse
from pptx import Presentation
from pptx.util import Inches, Pt, Emu
from pptx.dml.color import RGBColor
from pptx.enum.text import PP_ALIGN
from PIL import Image, ImageDraw, ImageFont
import io
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import numpy as np

def get_fonts():
    candidate_pairs = [
        ('C:/Windows/Fonts/arial.ttf', 'C:/Windows/Fonts/arialbd.ttf'),
        ('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'),
        ('/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf', '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf'),
        ('/usr/share/fonts/truetype/msttcorefonts/arial.ttf', '/usr/share/fonts/truetype/msttcorefonts/arialbd.ttf'),
        ('/usr/share/fonts/truetype/freefont/FreeSans.ttf', '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf'),
    ]
    for reg_path, bold_path in candidate_pairs:
        try:
            if os.path.exists(reg_path) and os.path.exists(bold_path):
                f_reg = ImageFont.truetype(reg_path, 13)
                f_bold = ImageFont.truetype(bold_path, 13)
                f_head = ImageFont.truetype(bold_path, 14)
                f_sm = ImageFont.truetype(reg_path, 11)
                f_sm_bold = ImageFont.truetype(bold_path, 11)
                return f_reg, f_bold, f_head, f_sm, f_sm_bold
        except Exception:
            continue

    f_reg = ImageFont.load_default()
    return f_reg, f_reg, f_reg, f_reg, f_reg


def draw_kpi_table(col_month, rows, green_rows=None, red_cells=None, width=982, row_height=28):
    if green_rows is None:
        green_rows = []
    if red_cells is None:
        red_cells = []

    f_reg, f_bold, f_head, _, _ = get_fonts()
    num_rows = len(rows) + 2
    total_height = num_rows * row_height

    img = Image.new('RGB', (width, total_height), color=(255, 255, 255))
    draw = ImageDraw.Draw(img)

    col_widths = [352, 60, 90, 115, 125, 115, 125]
    col_x = [0]
    for w in col_widths:
        col_x.append(col_x[-1] + w)

    # Header Row 1
    draw.rectangle([(col_x[3], 0), (col_x[5], row_height)], fill=(217, 217, 217), outline=(160, 160, 160))
    draw.rectangle([(col_x[5], 0), (col_x[7] - 1, row_height)], fill=(217, 217, 217), outline=(160, 160, 160))
    draw.text((col_x[3] + 60, 5), "Gestión 2026", fill=(0, 0, 0), font=f_head)
    draw.text((col_x[5] + 60, 5), "Gestión 2025", fill=(0, 0, 0), font=f_head)

    # Header Row 2
    y2 = row_height
    subheaders = [
        ("DETALLE", 0, col_widths[0], 'center'),
        ("U/M", 1, col_widths[1], 'center'),
        (col_month, 2, col_widths[2], 'center'),
        ("Promedio", 3, col_widths[3], 'center'),
        ("Total", 4, col_widths[4], 'center'),
        ("Promedio", 5, col_widths[5], 'center'),
        ("Total", 6, col_widths[6], 'center'),
    ]
    for text, c_idx, w, align in subheaders:
        x1 = col_x[c_idx]
        x2 = col_x[c_idx + 1]
        bg = (217, 217, 217) if c_idx >= 3 else (255, 255, 255)
        draw.rectangle([(x1, y2), (x2, y2 + row_height)], fill=bg, outline=(160, 160, 160))
        bbox = f_bold.getbbox(text)
        tw = bbox[2] - bbox[0]
        tx = x1 + (w - tw) // 2 if align == 'center' else x1 + 10
        draw.text((tx, y2 + 5), text, fill=(0, 0, 0), font=f_bold)

    # Data Rows
    for r_idx, row in enumerate(rows):
        y = (r_idx + 2) * row_height
        is_green = r_idx in green_rows
        row_bg = (178, 214, 178) if is_green else (255, 255, 255)

        for c_idx, val in enumerate(row):
            x1 = col_x[c_idx]
            x2 = col_x[c_idx + 1]
            w = col_widths[c_idx]
            draw.rectangle([(x1, y), (x2, y + row_height)], fill=row_bg, outline=(160, 160, 160))

            is_red = (r_idx, c_idx) in red_cells
            txt_color = (204, 0, 0) if is_red else (0, 0, 0)
            use_bold = is_green or (c_idx == 0) or is_red or (c_idx >= 2)
            f = f_bold if use_bold else f_reg

            str_val = str(val)
            bbox = f.getbbox(str_val)
            tw = bbox[2] - bbox[0]

            if c_idx == 0:
                tx = x1 + 8
            elif c_idx == 1:
                tx = x1 + (w - tw) // 2
            else:
                tx = x2 - tw - 12

            draw.text((tx, y + 5), str_val, fill=txt_color, font=f)

    draw.rectangle([(0, 0), (width - 1, total_height - 1)], outline=(120, 120, 120), width=1)
    
    buf = io.BytesIO()
    img.save(buf, format='PNG')
    return buf.getvalue()

def draw_delayed_orders_table(orders, width=1227, row_height=22):
    _, _, _, f_sm, f_sm_bold = get_fonts()
    headers = ["N° CC", "N° OT", "CLIENTE", "PRODUCTO / TIPO", "UNIDADES", "FECHA REQUERIDA", "DÍAS ATRASO", "ESTADO"]
    col_widths = [110, 95, 290, 240, 110, 140, 110, 132]
    col_x = [0]
    for w in col_widths:
        col_x.append(col_x[-1] + w)

    display_orders = orders[:8] if orders else []
    num_rows = max(len(display_orders), 1) + 1
    total_height = num_rows * row_height

    img = Image.new('RGB', (width, total_height), color=(255, 255, 255))
    draw = ImageDraw.Draw(img)

    for c_idx, h_text in enumerate(headers):
        x1 = col_x[c_idx]
        x2 = col_x[c_idx + 1]
        draw.rectangle([(x1, 0), (x2, row_height)], fill=(230, 230, 230), outline=(170, 170, 170))
        bbox = f_sm_bold.getbbox(h_text)
        tw = bbox[2] - bbox[0]
        draw.text((x1 + (col_widths[c_idx] - tw) // 2, 4), h_text, fill=(0, 0, 0), font=f_sm_bold)

    if not display_orders:
        draw.text((20, row_height + 4), "No se registran pedidos atrasados en el período.", fill=(0, 128, 0), font=f_sm_bold)
    else:
        for r_idx, o in enumerate(display_orders):
            y = (r_idx + 1) * row_height
            bg = (255, 245, 245) if (r_idx % 2 == 1) else (255, 255, 255)
            draw.rectangle([(0, y), (width - 1, y + row_height)], fill=bg, outline=(200, 200, 200))

            vals = [
                str(o.get('cc_number', '-')),
                str(o.get('ot_number', '-')),
                str(o.get('customer_name', '-'))[:35],
                str(o.get('product_type', '-'))[:30],
                f"{int(float(o.get('units', 0))):,}".replace(',', '.'),
                str(o.get('required_date', '-')),
                f"{int(float(o.get('delay_days', 0)))} días",
                "ATRASADO"
            ]

            for c_idx, val in enumerate(vals):
                x1 = col_x[c_idx]
                x2 = col_x[c_idx + 1]
                w = col_widths[c_idx]
                draw.rectangle([(x1, y), (x2, y + row_height)], outline=(200, 200, 200))
                is_delay = (c_idx in [6, 7])
                color = (180, 0, 0) if is_delay else (0, 0, 0)
                font = f_sm_bold if (c_idx in [0, 4, 6, 7]) else f_sm

                bbox = font.getbbox(val)
                tw = bbox[2] - bbox[0]
                if c_idx in [0, 1, 5, 6, 7]:
                    tx = x1 + (w - tw) // 2
                elif c_idx == 4:
                    tx = x2 - tw - 8
                else:
                    tx = x1 + 6

                draw.text((tx, y + 4), val, fill=color, font=font)

    buf = io.BytesIO()
    img.save(buf, format='PNG')
    return buf.getvalue()

def _finish_chart(plt_module):
    """Cierra la figura actual y devuelve los bytes PNG."""
    plt_module.tight_layout()
    buf = io.BytesIO()
    plt_module.savefig(buf, format='png', bbox_inches='tight', dpi=150)
    plt_module.close()
    buf.seek(0)
    return buf.getvalue()


def _style_axes(ax):
    """Estilo común de ejes (sin bordes superior/derecho, grilla suave)."""
    ax.spines['top'].set_visible(False)
    ax.spines['right'].set_visible(False)
    ax.spines['left'].set_color('#CCCCCC')
    ax.spines['bottom'].set_color('#CCCCCC')
    ax.yaxis.grid(True, linestyle='--', alpha=0.4, color='#CCCCCC')
    ax.set_axisbelow(True)


def _mean_present(values):
    """Promedio ignorando meses sin dato (None)."""
    present = [float(v) for v in values if v is not None]
    return float(np.mean(present)) if present else 0.0


def generate_prod_chart(labels, prod, cap):
    """Producción real vs capacidad productiva: una barra doble por mes (enero hasta el mes del informe) + Prom."""
    months = list(labels) + ['Prom']
    prod = [int(v) for v in prod]
    cap = [int(v) for v in cap]
    prod.append(int(np.mean(prod)))
    cap.append(int(np.mean(cap)))

    x = np.arange(len(months))
    width = 0.38
    fig, ax = plt.subplots(figsize=(7.5 if len(months) <= 10 else 9.0, 4.0), dpi=150)
    fig.patch.set_facecolor('white')
    ax.set_facecolor('white')
    c_cyan = '#22C5C3'
    c_gray = '#B0B0B0'

    ax.bar(x - width/2, prod, width, label='Producción Mes', color=c_cyan)
    ax.bar(x + width/2, cap, width, label='Capacidad Productiva Máquinas', color=c_gray)
    ax.set_title('Capacidad productiva vs. Producción real', fontsize=14, fontweight='bold', color='#333333', pad=18)
    ax.set_xticks(x)
    ax.set_xticklabels(months, fontsize=10 if len(months) <= 10 else 8.5)
    ax.yaxis.set_major_formatter(plt.FuncFormatter(lambda val, loc: f"{int(val):,}".replace(',', '.')))
    top = max(max(prod), max(cap))
    ax.set_ylim(0, top * 1.2)
    _style_axes(ax)
    for i, (p, c) in enumerate(zip(prod, cap)):
        ax.text(x[i] - width/2, p + top * 0.015, f"{p:,}".replace(',', '.'), ha='center', va='bottom', fontsize=7.5, fontweight='bold', color='#111111')
        ax.text(x[i] + width/2, c + top * 0.015, f"{c:,}".replace(',', '.'), ha='center', va='bottom', fontsize=7.5, color='#444444')
    ax.legend(loc='upper center', bbox_to_anchor=(0.5, -0.14), ncol=2, frameon=False, fontsize=9.5)
    return _finish_chart(plt)


def generate_sl_units_chart(labels, tot, del_u, pct):
    """Nivel de servicio en unidades: pedidos totales vs atrasados por mes + % de cumplimiento + Prom."""
    months = list(labels) + ['Prom']
    tot = [int(v) for v in tot]
    del_u = [int(v) for v in del_u]
    pct = [float(v) for v in pct]
    tot.append(int(np.mean(tot)))
    del_u.append(int(np.mean(del_u)))
    pct.append(round(float(np.mean(pct)), 2))

    x = np.arange(len(months))
    width = 0.38
    fig, ax = plt.subplots(figsize=(8.0 if len(months) <= 10 else 9.5, 4.2), dpi=150)
    fig.patch.set_facecolor('white')
    ax.set_facecolor('white')
    c_cyan = '#22C5C3'
    c_gray = '#B0B0B0'

    ax.bar(x - width/2, tot, width, label='Total unidades Pedidos Ud/Mes', color=c_cyan)
    ax.bar(x + width/2, del_u, width, label='Total unidades Pedidos Atrasados Ud/Mes', color=c_gray)
    ax.set_title('Nivel de servicio total de planta [Unid.]', fontsize=14, fontweight='bold', color='#333333', pad=18)
    ax.set_xticks(x)
    ax.set_xticklabels(months, fontsize=10 if len(months) <= 10 else 8.5)
    ax.yaxis.set_major_formatter(plt.FuncFormatter(lambda val, loc: f"{int(val):,}".replace(',', '.')))
    top = max(max(tot), 1)
    ax.set_ylim(0, top * 1.2)
    _style_axes(ax)
    for i in range(len(months)):
        ax.text(x[i] - width/2, tot[i] + top * 0.015, f"{tot[i]:,}".replace(',', '.'), ha='center', va='bottom', fontsize=7.0, fontweight='bold', color='#111111')
        ax.text(x[i] + width/2, del_u[i] + top * 0.015, f"{del_u[i]:,}".replace(',', '.'), ha='center', va='bottom', fontsize=6.8, color='#444444')
        ax.text(x[i], top * 0.11, f"{pct[i]:.2f}%".replace('.', ','), ha='center', va='center', fontsize=7.2, fontweight='bold', color='#0f766e',
                bbox=dict(boxstyle='square,pad=0.2', facecolor='white', edgecolor='#22C5C3', alpha=0.9))
    ax.legend(loc='upper center', bbox_to_anchor=(0.5, -0.14), ncol=2, frameon=False, fontsize=9.5)
    return _finish_chart(plt)


def generate_sl_cc_chart(labels, tot, del_cc, pct):
    """Nivel de servicio por centro de costo (pedidos): barras totales/atrasados + línea de % + Prom."""
    months = list(labels) + ['Prom']
    tot = [int(v) for v in tot]
    del_cc = [int(v) for v in del_cc]
    pct = [float(v) for v in pct]
    tot.append(int(np.mean(tot)))
    del_cc.append(int(np.mean(del_cc)))
    pct.append(round(float(np.mean(pct)), 2))

    x = np.arange(len(months))
    width = 0.35
    fig, ax1 = plt.subplots(figsize=(8.0 if len(months) <= 10 else 9.5, 4.2), dpi=150)
    fig.patch.set_facecolor('white')
    ax1.set_facecolor('white')
    ax2 = ax1.twinx()
    c_cyan = '#22C5C3'
    c_gray = '#B0B0B0'
    c_orange = '#F59E0B'

    ax1.bar(x - width/2, tot, width, label='Total Pedidos  CC/Mes', color=c_cyan)
    ax1.bar(x + width/2, del_cc, width, label='Total Pedidos Atrasados CC/Mes', color=c_gray)
    ax2.plot(x, pct, color=c_orange, linewidth=2.5, marker='o', markersize=4, label='Nivel de Servicio.   %')
    ax1.set_title('Nivel de servicio total de planta [C.C.]', fontsize=14, fontweight='bold', color='#333333', pad=18)
    ax1.set_xticks(x)
    ax1.set_xticklabels(months, fontsize=10 if len(months) <= 10 else 8.5)
    top = max(max(tot), 1)
    ax1.set_ylim(0, top * 1.35)
    ax2.set_ylim(0, 120)
    ax2.yaxis.set_major_formatter(plt.FuncFormatter(lambda val, loc: f"{int(val)}%"))
    ax1.spines['top'].set_visible(False)
    ax2.spines['top'].set_visible(False)
    ax1.spines['left'].set_color('#CCCCCC')
    ax1.spines['bottom'].set_color('#CCCCCC')
    ax1.yaxis.grid(True, linestyle='--', alpha=0.4, color='#CCCCCC')
    ax1.set_axisbelow(True)
    for i in range(len(months)):
        ax1.text(x[i] - width/2, tot[i] + top * 0.02, str(tot[i]), ha='center', va='bottom', fontsize=7.2, fontweight='bold', color='#111111')
        ax1.text(x[i] + width/2, del_cc[i] + top * 0.02, str(del_cc[i]), ha='center', va='bottom', fontsize=7.0, color='#444444')
        ax2.text(x[i], pct[i] - 12, f"{pct[i]:.1f}%", ha='center', va='bottom', fontsize=7.2, fontweight='bold', color='#B45309')
    lines, labs = ax1.get_legend_handles_labels()
    lines2, labs2 = ax2.get_legend_handles_labels()
    ax1.legend(lines + lines2, labs + labs2, loc='upper center', bbox_to_anchor=(0.5, -0.14), ncol=3, frameon=False, fontsize=9.0)
    return _finish_chart(plt)


def generate_waste_chart(labels, real):
    """Total de merma: meta (1,60 %) vs % real vs margen, por mes + Prom."""
    months = list(labels) + ['Prom']
    real = [float(v) for v in real]
    target = [1.60] * len(months)
    margin = [round(t - r, 2) for t, r in zip(target[:len(real)], real)]
    prom_real = round(float(np.mean(real)), 2)
    real.append(prom_real)
    margin.append(round(1.60 - prom_real, 2))

    x = np.arange(len(months))
    width = 0.28
    fig, ax = plt.subplots(figsize=(8.0 if len(months) <= 10 else 9.5, 4.2), dpi=150)
    fig.patch.set_facecolor('white')
    ax.set_facecolor('white')
    c_cyan = '#22C5C3'
    c_gray = '#B0B0B0'
    c_yellow = '#F59E0B'

    ax.bar(x - width, target, width, label='% A Cumplir (Merma)', color=c_cyan)
    ax.bar(x, real, width, label='% Real', color=c_gray)
    ax.bar(x + width, margin, width, label='Margen (+/-)', color=c_yellow)
    ax.set_title('Total de Merma', fontsize=14, fontweight='bold', color='#333333', pad=18)
    ax.set_xticks(x)
    ax.set_xticklabels(months, fontsize=10 if len(months) <= 10 else 8.5)
    ax.yaxis.set_major_formatter(plt.FuncFormatter(lambda val, loc: f"{val:.2f}%".replace('.', ',')))
    ax.set_ylim(min(0, min(margin) - 0.1), max(1.95, max(real) * 1.2))
    _style_axes(ax)
    for i in range(len(months)):
        ax.text(x[i] - width, target[i] + 0.03, "1,60%", ha='center', va='bottom', fontsize=6.8, fontweight='bold', color='#111111')
        ax.text(x[i], real[i] + 0.03, f"{real[i]:.2f}%".replace('.', ','), ha='center', va='bottom', fontsize=6.8, color='#333333')
        ax.text(x[i] + width, max(margin[i], 0) + 0.03, f"{margin[i]:.2f}%".replace('.', ','), ha='center', va='bottom', fontsize=6.8, fontweight='bold', color='#B45309')
    ax.legend(loc='upper center', bbox_to_anchor=(0.5, -0.14), ncol=3, frameon=False, fontsize=9.5)
    return _finish_chart(plt)


def generate_process_balance_chart(title_name, labels, label_prog, label_proc, label_waste, label_rate,
                                   prog, proc, waste, y1_max=None, y2_max=None):
    """Balance de masa de un proceso: kg programados / procesados / merma por mes + línea de % de merma + Prom.
    Los meses sin dato vienen como None (se dibujan en cero y no entran al promedio)."""
    n = len(labels)
    prog_v = [float(v) if v is not None else 0.0 for v in prog]
    proc_v = [float(v) if v is not None else 0.0 for v in proc]
    waste_v = [float(v) if v is not None else 0.0 for v in waste]
    pct = [w / p * 100.0 if p > 0 else 0.0 for w, p in zip(waste_v, proc_v)]
    prom_prog = _mean_present(prog)
    prom_proc = _mean_present(proc)
    prom_waste = _mean_present(waste)
    prom_pct = _mean_present([pc if proc[i] is not None else None for i, pc in enumerate(pct)])
    if y1_max is None:
        y1_max = max(max(prog_v + proc_v + [prom_prog, prom_proc]), 1.0) * 1.2
    if y2_max is None:
        y2_max = max(max(pct + [prom_pct]), 0.1) * 1.5

    x_months = np.arange(n)
    x_prom = n + 0.8
    all_x = list(x_months) + [x_prom]
    all_labels = list(labels) + ['Prom']

    fig, ax1 = plt.subplots(figsize=(7.8 if n <= 9 else 9.5, 4.2), dpi=150)
    fig.patch.set_facecolor('white')
    ax1.set_facecolor('white')
    ax2 = ax1.twinx()
    width = 0.25
    c_cyan = '#22C5C3'
    c_gray = '#B0B0B0'
    c_blue = '#3B82F6'
    c_orange = '#F59E0B'

    b_prog = ax1.bar(x_months - width, prog_v, width, label=label_prog, color=c_cyan)
    ax1.bar(x_months, proc_v, width, label=label_proc, color=c_gray)
    b_waste = ax1.bar(x_months + width, waste_v, width, label=label_waste, color=c_blue)
    b_proc = ax1.containers[1]
    ax1.bar(x_prom - width, prom_prog, width, color=c_cyan)
    ax1.bar(x_prom, prom_proc, width, color=c_gray)
    ax1.bar(x_prom + width, prom_waste, width, color=c_blue)
    l_rate = ax2.plot(x_months, pct, color=c_orange, linewidth=2.8, label=label_rate)[0]
    ax2.plot([x_prom], [prom_pct], color=c_orange, marker='o', markersize=4.5)
    for i in range(n):
        va_mode = 'bottom' if (i % 2 == 0 or pct[i] > 0.8) else 'top'
        y_offset = (y2_max * 0.04) if va_mode == 'bottom' else -(y2_max * 0.04)
        ax2.text(x_months[i], pct[i] + y_offset, f'{pct[i]:.2f}%'.replace('.', ','), ha='center', va=va_mode, fontsize=7.0, fontweight='bold', color='#111111')
    ax2.text(x_prom, prom_pct + (y2_max * 0.04), f'{prom_pct:.2f}%'.replace('.', ','), ha='center', va='bottom', fontsize=7.2, fontweight='bold', color='#111111')

    ax1.set_title(title_name, fontsize=13, fontweight='bold', color='#333333', pad=14)
    ax1.set_xticks(all_x)
    ax1.set_xticklabels(all_labels, fontsize=9 if n <= 9 else 8)
    ax1.set_xlim(-0.7, n + 1.7)
    ax1.set_ylim(0, y1_max)
    ax2.set_ylim(0, y2_max)
    ax1.yaxis.set_major_formatter(plt.FuncFormatter(lambda val, loc: f'{int(val):,}'.replace(',', '.')))
    ax2.yaxis.set_major_formatter(plt.FuncFormatter(lambda val, loc: f'{val:.2f}%'.replace('.', ',')))
    for axis in (ax1, ax2):
        axis.spines['top'].set_visible(False)
        for side in ('left', 'right', 'bottom'):
            axis.spines[side].set_color('#CCCCCC')
    ax1.yaxis.grid(True, linestyle='--', alpha=0.4, color='#CCCCCC')
    ax1.set_axisbelow(True)
    ax1.legend([b_prog, b_waste, b_proc, l_rate], [label_prog, label_waste, label_proc, label_rate],
               loc='upper center', bbox_to_anchor=(0.5, -0.15), ncol=2, frameon=False, fontsize=8.0)
    return _finish_chart(plt)


def update_text_in_shape(shape, old_text, new_text):
    if shape.has_text_frame:
        for paragraph in shape.text_frame.paragraphs:
            for run in paragraph.runs:
                if old_text in run.text:
                    run.text = run.text.replace(old_text, new_text)

def replace_picture_blob(slide, picture_name, new_png_bytes):
    for shape in slide.shapes:
        if shape.name == picture_name:
            rId = shape._element.blip_rId
            rel_part = shape.part.related_part(rId)
            rel_part._blob = new_png_bytes
            return True
    return False

def replace_picture_with_native_table(slide, picture_name, col_month, rows, green_rows=None, red_cells=None, fallback_png=None):
    if green_rows is None:
        green_rows = []
    if red_cells is None:
        red_cells = []

    target = None
    for s in list(slide.shapes):
        if s.name == picture_name:
            target = s
            break
    if not target:
        if fallback_png:
            replace_picture_blob(slide, picture_name, fallback_png)
        return False

    left = target.left
    top = target.top
    width = target.width
    height = target.height
    slide.shapes._spTree.remove(target._element)

    num_rows = len(rows) + 2
    num_cols = 7
    tbl_shape = slide.shapes.add_table(num_rows, num_cols, left, top, width, height)
    tbl = tbl_shape.table

    col_pcts = [0.358, 0.061, 0.092, 0.117, 0.127, 0.117, 0.128]
    for c_idx, pct in enumerate(col_pcts):
        tbl.columns[c_idx].width = int(width * pct)

    for c in range(num_cols):
        cell = tbl.cell(0, c)
        cell.fill.solid()
        cell.fill.fore_color.rgb = RGBColor(217, 217, 217)

    c3 = tbl.cell(0, 3)
    c3.text = "Gestión 2026"
    for p in c3.text_frame.paragraphs:
        p.alignment = PP_ALIGN.CENTER
        for r in p.runs:
            r.font.name = "Arial"
            r.font.size = Pt(10)
            r.font.bold = True
            r.font.color.rgb = RGBColor(0, 0, 0)

    c5 = tbl.cell(0, 5)
    c5.text = "Gestión 2025"
    for p in c5.text_frame.paragraphs:
        p.alignment = PP_ALIGN.CENTER
        for r in p.runs:
            r.font.name = "Arial"
            r.font.size = Pt(10)
            r.font.bold = True
            r.font.color.rgb = RGBColor(0, 0, 0)

    subheaders = [
        ("DETALLE", PP_ALIGN.CENTER),
        ("U/M", PP_ALIGN.CENTER),
        (col_month, PP_ALIGN.CENTER),
        ("Promedio", PP_ALIGN.CENTER),
        ("Total", PP_ALIGN.CENTER),
        ("Promedio", PP_ALIGN.CENTER),
        ("Total", PP_ALIGN.CENTER),
    ]
    for c_idx, (text, align) in enumerate(subheaders):
        cell = tbl.cell(1, c_idx)
        cell.fill.solid()
        cell.fill.fore_color.rgb = RGBColor(217, 217, 217) if c_idx >= 3 else RGBColor(255, 255, 255)
        cell.text = text
        for p in cell.text_frame.paragraphs:
            p.alignment = align
            for r in p.runs:
                r.font.name = "Arial"
                r.font.size = Pt(10)
                r.font.bold = True
                r.font.color.rgb = RGBColor(0, 0, 0)

    for r_idx, row_vals in enumerate(rows):
        tr_idx = r_idx + 2
        is_green = r_idx in green_rows
        bg = RGBColor(178, 214, 178) if is_green else RGBColor(255, 255, 255)
        for c_idx, val in enumerate(row_vals):
            cell = tbl.cell(tr_idx, c_idx)
            cell.fill.solid()
            cell.fill.fore_color.rgb = bg
            cell.text = str(val)
            is_red = (r_idx, c_idx) in red_cells
            txt_color = RGBColor(204, 0, 0) if is_red else RGBColor(0, 0, 0)
            use_bold = is_green or (c_idx == 0) or is_red or (c_idx >= 2)
            for p in cell.text_frame.paragraphs:
                p.alignment = PP_ALIGN.LEFT if c_idx == 0 else (PP_ALIGN.CENTER if c_idx == 1 else PP_ALIGN.RIGHT)
                for r in p.runs:
                    r.font.name = "Arial"
                    r.font.size = Pt(9.5)
                    r.font.bold = use_bold
                    r.font.color.rgb = txt_color
    return True

def replace_picture_with_delayed_table(slide, picture_name, orders, fallback_png=None):
    target = None
    for s in list(slide.shapes):
        if s.name == picture_name:
            target = s
            break
    if not target:
        if fallback_png:
            replace_picture_blob(slide, picture_name, fallback_png)
        return False

    left = target.left
    top = target.top
    width = target.width
    height = target.height
    slide.shapes._spTree.remove(target._element)

    headers = ["N° CC", "N° OT", "CLIENTE", "PRODUCTO / TIPO", "UNIDADES", "FECHA REQUERIDA", "DÍAS ATRASO", "ESTADO"]
    col_pcts = [0.09, 0.08, 0.24, 0.20, 0.09, 0.12, 0.09, 0.09]
    display_orders = orders[:8] if orders else []
    num_rows = max(len(display_orders), 1) + 1
    num_cols = len(headers)

    tbl_shape = slide.shapes.add_table(num_rows, num_cols, left, top, width, height)
    tbl = tbl_shape.table
    for c_idx, pct in enumerate(col_pcts):
        tbl.columns[c_idx].width = int(width * pct)

    for c_idx, h_text in enumerate(headers):
        cell = tbl.cell(0, c_idx)
        cell.fill.solid()
        cell.fill.fore_color.rgb = RGBColor(230, 230, 230)
        cell.text = h_text
        for p in cell.text_frame.paragraphs:
            p.alignment = PP_ALIGN.CENTER
            for r in p.runs:
                r.font.name = "Arial"
                r.font.size = Pt(9.5)
                r.font.bold = True
                r.font.color.rgb = RGBColor(0, 0, 0)

    if not display_orders:
        cell = tbl.cell(1, 0)
        cell.text = "No se registran pedidos atrasados en el período."
        for p in cell.text_frame.paragraphs:
            for r in p.runs:
                r.font.name = "Arial"
                r.font.size = Pt(9)
                r.font.bold = True
                r.font.color.rgb = RGBColor(0, 128, 0)
    else:
        for r_idx, o in enumerate(display_orders):
            tr_idx = r_idx + 1
            row_bg = RGBColor(255, 245, 245) if (r_idx % 2 == 1) else RGBColor(255, 255, 255)
            vals = [
                str(o.get('cc_number', '-')),
                str(o.get('ot_number', '-')),
                str(o.get('customer_name', '-'))[:35],
                str(o.get('product_type', '-'))[:30],
                f"{int(float(o.get('units', 0))):,}".replace(',', '.'),
                str(o.get('required_date', '-')),
                f"{int(float(o.get('delay_days', 0)))} días",
                "ATRASADO"
            ]
            for c_idx, val in enumerate(vals):
                cell = tbl.cell(tr_idx, c_idx)
                cell.fill.solid()
                cell.fill.fore_color.rgb = row_bg
                cell.text = val
                is_delay = (c_idx in [6, 7])
                txt_color = RGBColor(180, 0, 0) if is_delay else RGBColor(0, 0, 0)
                use_bold = (c_idx in [0, 4, 6, 7])
                for p in cell.text_frame.paragraphs:
                    p.alignment = PP_ALIGN.CENTER if c_idx in [0, 1, 5, 6, 7] else (PP_ALIGN.RIGHT if c_idx == 4 else PP_ALIGN.LEFT)
                    for r in p.runs:
                        r.font.name = "Arial"
                        r.font.size = Pt(8.5)
                        r.font.bold = use_bold
                        r.font.color.rgb = txt_color
    return True

MESES_ABR = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic']
MESES_NOM = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE']


def month_info(month_key):
    """('NOVIEMBRE 2026', 'nov-26') para cualquier mes 'YYYY-MM' (antes solo existían agosto, septiembre y octubre)."""
    year, month = month_key.split('-')
    year, month = int(year), int(month)
    return f"{MESES_NOM[month - 1]} {year}", f"{MESES_ABR[month - 1]}-{str(year)[2:]}"


def fmt_int(value):
    """Entero con separador de miles chileno: 1.234.567"""
    return f"{int(round(float(value))):,}".replace(',', '.')


def fmt_pct(value, decimals=2):
    """Porcentaje con coma decimal: 12,34%"""
    return f"{float(value):.{decimals}f}%".replace('.', ',')


def fmt_dec(value, decimals=1):
    """Decimal con coma: 12,3"""
    return f"{float(value):.{decimals}f}".replace('.', ',')


def main():
    parser = argparse.ArgumentParser(description="Generador Mensual PPTX Unibag")
    parser.add_argument("--template", default=os.path.join("data", "KP´S OPERACIONES AGOSTO 2026.pptx"))
    parser.add_argument("--month", default="")
    parser.add_argument("--data", default="")
    parser.add_argument("--output", default=os.path.join("data", "KPIS_OPERACIONES.pptx"))
    parser.add_argument("--all-slides", action="store_true", default=False, help="Incluir todas las diapositivas del template en vez de solo las modificadas")
    args = parser.parse_args()

    if not os.path.exists(args.template):
        print(f"Error: Template no encontrado: {args.template}")
        sys.exit(1)

    data = {}
    if args.data and os.path.exists(args.data):
        with open(args.data, "r", encoding="utf-8") as f:
            data = json.load(f)
    elif args.data:
        try:
            data = json.loads(args.data)
        except Exception:
            data = {}

    # El historial (enero hasta el mes del informe) lo entrega el sistema: nunca se inventan cifras.
    hist = data.get("history") or []
    month_key = args.month or str(data.get("month_key", ""))
    if not hist or not month_key:
        print("Error: faltan datos (history / month). Genere el informe desde el sistema para que se calculen desde el ERP.")
        sys.exit(1)

    month_name, month_col = month_info(month_key)
    labels = [month_info(h["month_key"])[1] for h in hist]
    n_months = len(hist)
    print(f"Generando presentacion para: {month_name} ({month_col}) con {n_months} meses de historial")
    prs = Presentation(args.template)
    missing = []  # imágenes de la plantilla que no se encontraron (si hay alguna, el informe quedaría desactualizado)

    # 1. Títulos: la plantilla trae "AGOSTO 2026"; se reemplaza por el mes pedido.
    for slide in prs.slides:
        for shape in slide.shapes:
            update_text_in_shape(shape, "AGOSTO 2026", month_name)
            update_text_in_shape(shape, "Agosto 2026", month_name.capitalize())
            update_text_in_shape(shape, "REPORTE DE OPERACIONES AGOSTO 2026", f"REPORTE DE OPERACIONES {month_name}")

    def put_chart(slide_idx, picture_name, png):
        if not replace_picture_blob(prs.slides[slide_idx], picture_name, png):
            missing.append(f"diapositiva {slide_idx + 1}: {picture_name} (gráfico)")

    def put_table(slide_idx, picture_name, rows, green_rows=None, red_cells=None):
        png = draw_kpi_table(col_month=month_col, rows=rows, green_rows=green_rows, red_cells=red_cells)
        if not replace_picture_with_native_table(prs.slides[slide_idx], picture_name, month_col, rows,
                                                 green_rows=green_rows, red_cells=red_cells, fallback_png=png):
            missing.append(f"diapositiva {slide_idx + 1}: {picture_name} (tabla)")

    red_row0 = [(1, 2), (1, 3), (1, 4), (1, 5), (1, 6)]

    # ---------------- Producción (diapositiva 2) ----------------
    prod = [float(h["prod_units"]) for h in hist]
    cap = [float(h.get("prod_cap", data.get("prod_cap", 0))) for h in hist]
    prod_units = prod[-1]
    prod_meta = float(data.get("prod_meta", 1000000))
    prod_cumpl = (prod_units / prod_meta * 100.0) if prod_meta > 0 else 0.0
    ytd_prod_total = sum(prod)
    ytd_prod_prom = ytd_prod_total / n_months
    ytd_cumpl = (ytd_prod_prom / prod_meta * 100.0) if prod_meta > 0 else 0.0
    prod_rows = [
        ["Meta Planta reflejada en Unidades", "Un/Mes", fmt_int(prod_meta), "1.000.000", "12.000.000", "958.333", "11.500.000"],
        ["% cumplimiento Meta", "%", fmt_pct(prod_cumpl), fmt_pct(ytd_cumpl), fmt_pct(ytd_cumpl), "100,60%", "100,60%"],
        ["Producción Mes", "Und", fmt_int(prod_units), fmt_int(ytd_prod_prom), fmt_int(ytd_prod_total), "964.880", "11.569.083"],
    ]
    put_table(1, 'Imagen 6', prod_rows, green_rows=[2], red_cells=red_row0)
    put_chart(1, 'Imagen 9', generate_prod_chart(labels, prod, cap))

    # ---------------- Nivel de servicio en unidades (diapositiva 5) ----------------
    sl_tot = [float(h["sl_total_units"]) for h in hist]
    sl_del = [float(h["sl_delayed_units"]) for h in hist]
    sl_pct = [((t - d) / t * 100.0) if t > 0 else 0.0 for t, d in zip(sl_tot, sl_del)]
    ytd_tot, ytd_del = sum(sl_tot), sum(sl_del)
    ytd_sl_pct = ((ytd_tot - ytd_del) / ytd_tot * 100.0) if ytd_tot > 0 else 0.0
    sl_u_rows = [
        ["Total unidades Pedidos", "Ud/Mes", fmt_int(sl_tot[-1]), fmt_int(ytd_tot / n_months), fmt_int(ytd_tot), "971.715", "11.660.579"],
        ["Total unidades Pedidos Atrasados", "Ud/Mes", fmt_int(sl_del[-1]), fmt_int(ytd_del / n_months), fmt_int(ytd_del), "16.937", "203.243"],
        ["Nivel de Servicio.", "%", fmt_pct(sl_pct[-1]), fmt_pct(ytd_sl_pct), fmt_pct(ytd_sl_pct), "98,42%", "1181,01%"],
    ]
    put_table(4, 'Imagen 8', sl_u_rows, green_rows=[2], red_cells=red_row0)
    put_chart(4, 'Imagen 10', generate_sl_units_chart(labels, sl_tot, sl_del, sl_pct))

    # ---------------- Nivel de servicio por pedidos / C.C. (diapositiva 6) ----------------
    cc_tot = [int(h["sl_total_cc"]) for h in hist]
    cc_del = [int(h["sl_delayed_cc"]) for h in hist]
    cc_pct = [((t - d) / t * 100.0) if t > 0 else 0.0 for t, d in zip(cc_tot, cc_del)]
    ytd_cc_tot, ytd_cc_del = sum(cc_tot), sum(cc_del)
    ytd_cc_pct = ((ytd_cc_tot - ytd_cc_del) / ytd_cc_tot * 100.0) if ytd_cc_tot > 0 else 0.0
    sl_cc_rows = [
        ["Total Pedidos", "CC/Mes", str(cc_tot[-1]), fmt_int(ytd_cc_tot / n_months), str(ytd_cc_tot), "78", "940"],
        ["Total Pedidos Atrasados", "CC/Mes", str(cc_del[-1]), fmt_int(ytd_cc_del / n_months), str(ytd_cc_del), "5", "54"],
        ["Nivel de Servicio.", "%", fmt_pct(cc_pct[-1]), fmt_pct(ytd_cc_pct), fmt_pct(ytd_cc_pct), "94,85%", "94,26%"],
    ]
    put_table(5, 'Imagen 9', sl_cc_rows, green_rows=[2], red_cells=red_row0)
    put_chart(5, 'Imagen 11', generate_sl_cc_chart(labels, cc_tot, cc_del, cc_pct))

    # ---------------- Detalle de pedidos atrasados (diapositiva 7) ----------------
    delayed_orders = data.get("delayed_orders", [])
    t7_png = draw_delayed_orders_table(delayed_orders)
    if not replace_picture_with_delayed_table(prs.slides[6], 'Imagen 7', delayed_orders, fallback_png=t7_png):
        missing.append("diapositiva 7: Imagen 7 (tabla de atrasados)")

    # ---------------- Balance de masa por proceso (diapositivas 8 a 11) ----------------
    # No existe una fuente automática de kg programados/procesados por proceso: se usan solo si el mes
    # los trae en data/kpi_historial_2026.json; si no, esas 4 diapositivas se omiten (no se muestran cifras viejas).
    process_defs = [
        # clave, diapositiva, título, rótulos, filas de tabla
        ('flexo', 7, 'Balance de masa [Flexografia]', 'Kgs programados en Impresión (Flexos)', 'Kgs procesados en Impresión (Flexos)', 'Kgs Merma en Flexos', '% de merma en Flexos', 0),
        ('seri', 8, 'Balance de masa [Serigrafia]', 'Kgs programados en Impresión (Seris)', 'Kgs procesados en Impresión (Seris)', 'Kgs Merma en Seris', '% de merma en Seris', 1),
        ('cys', 9, 'Balance de masa [Corte y Sellado]', 'Kgs programados en Corte y Sellado', 'Kgs procesados en Corte y Sellado', 'Kgs Merma en Cortadoras', '% de merma en Corte y Sellado', 0),
        ('emb', 10, 'Balance de masa [Embalaje]', 'Kgs programados en Embalaje', 'Kgs procesados en Embalaje', 'Kgs Merma Embalaje', '% de merma Embalaje', 1),
    ]
    # Columnas "Gestión 2025" (valores históricos fijos de la plantilla, no se recalculan):
    # por proceso, 4 filas (programados, procesados, merma, % merma) con (promedio 2025, total 2025).
    gestion_2025 = {
        'flexo': [("38.423", "461.073"), ("38.372", "460.460"), ("264", "3.172"), ("0,70%", "8,37%")],
        'seri': [("3.216", "38.597"), ("3.138", "37.660"), ("19", "229"), ("0,69%", "8,26%")],
        'cys': [("44.025", "528.294"), ("44.528", "534.336"), ("123", "1.474"), ("0,28%", "3,32%")],
        'emb': [("43.587", "523.047"), ("43.661", "523.926"), ("115", "1.385"), ("0,27%", "3,24%")],
    }
    has_process = bool(data.get("has_process_balance"))
    if has_process:
        for key, slide_idx, title, l_prog, l_proc, l_waste, l_rate, waste_decimals in process_defs:
            def series(field):
                return [(h.get("procesos") or {}).get(key, {}).get(field) for h in hist]
            prog, proc, waste = series("prog"), series("proc"), series("waste")
            cur_prog, cur_proc, cur_waste = float(prog[-1] or 0), float(proc[-1] or 0), float(waste[-1] or 0)
            rate = (cur_waste / cur_proc * 100.0) if cur_proc > 0 else 0.0
            ok_idx = [i for i in range(n_months) if proc[i] is not None]
            sum_prog = sum(float(prog[i] or 0) for i in ok_idx)
            sum_proc = sum(float(proc[i] or 0) for i in ok_idx)
            sum_waste = sum(float(waste[i] or 0) for i in ok_idx)
            cnt = max(len(ok_idx), 1)
            pct_list = [(float(waste[i] or 0) / float(proc[i]) * 100.0) if float(proc[i] or 0) > 0 else 0.0 for i in ok_idx]
            prom_pct = (sum(pct_list) / len(pct_list)) if pct_list else 0.0
            tot_pct = (sum_waste / sum_proc * 100.0) if sum_proc > 0 else 0.0
            waste_fmt = (lambda v: fmt_dec(v, 1)) if waste_decimals else fmt_int
            g25 = gestion_2025[key]
            rows = [
                [l_prog, "kgs", fmt_int(cur_prog), fmt_int(sum_prog / cnt), fmt_int(sum_prog), *g25[0]],
                [l_proc, "kgs", fmt_int(cur_proc), fmt_int(sum_proc / cnt), fmt_int(sum_proc), *g25[1]],
                [l_waste, "kgs", waste_fmt(cur_waste), waste_fmt(sum_waste / cnt), waste_fmt(sum_waste), *g25[2]],
                [l_rate, "%", fmt_pct(rate), fmt_pct(prom_pct), fmt_pct(tot_pct), *g25[3]],
            ]
            put_table(slide_idx, 'Imagen 9', rows, red_cells=[(3, 2), (3, 3), (3, 4), (3, 5), (3, 6)])
            put_chart(slide_idx, 'Imagen 12', generate_process_balance_chart(
                title, labels, f"{l_prog} kgs", f"{l_proc} kgs", f"{l_waste} kgs", f"{l_rate} %", prog, proc, waste))
    else:
        print("AVISO: el mes no tiene kg programados/procesados por proceso en el historial: se omiten las diapositivas de Balance de masa.")

    # ---------------- Total de merma (diapositiva 12) ----------------
    waste_series = [float(h["waste_pct"]) for h in hist]
    put_chart(11, 'Imagen 9', generate_waste_chart(labels, waste_series))

    if missing:
        print("ERROR: la plantilla no contiene estos elementos, el informe quedaría desactualizado:")
        for item in missing:
            print("  - " + item)
        sys.exit(2)

    # Conservar únicamente las diapositivas de operaciones (sin Balance de masa si no hay datos).
    if not args.all_slides:
        keep_indices = {0, 1, 4, 5, 6, 11}
        if has_process:
            keep_indices |= {7, 8, 9, 10}
        for i in range(len(prs.slides) - 1, -1, -1):
            if i not in keep_indices:
                rId = prs.slides._sldIdLst[i].rId
                prs.part.drop_rel(rId)
                del prs.slides._sldIdLst[i]

    saved_path = args.output
    try:
        prs.save(args.output)
    except PermissionError:
        base, ext = os.path.splitext(args.output)
        saved_path = f"{base}_SOLO_MODIFICADAS{ext}"
        prs.save(saved_path)

    print(f"Presentacion generada con exito: {saved_path} ({os.path.getsize(saved_path)} bytes, {len(prs.slides)} diapositivas)")


if __name__ == "__main__":
    main()
