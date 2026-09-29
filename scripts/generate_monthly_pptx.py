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

def generate_prod_chart(sep_prod=903219, sep_cap=1345000):
    months = ['ene-26', 'feb-26', 'mar-26', 'abr-26', 'may-26', 'jun-26', 'jul-26', 'ago-26', 'sep-26', 'Prom']
    prod = [1154072, 1381920, 1224388, 1087851, 981258, 781553, 923649, 1070866, int(sep_prod)]
    cap =  [1411725, 1345556, 1482509, 1277703, 1209915, 1344581, 1479976, 1343895, int(sep_cap)]

    prom_prod = int(np.mean(prod))
    prom_cap = int(np.mean(cap))
    prod.append(prom_prod)
    cap.append(prom_cap)

    x = np.arange(len(months))
    width = 0.38

    fig, ax = plt.subplots(figsize=(7.5, 4.0), dpi=150)
    fig.patch.set_facecolor('white')
    ax.set_facecolor('white')

    c_cyan = '#22C5C3'
    c_gray = '#B0B0B0'

    ax.bar(x - width/2, prod, width, label='Producción Mes', color=c_cyan)
    ax.bar(x + width/2, cap, width, label='Capacidad Productiva Máquinas', color=c_gray)

    ax.set_title('Capacidad productiva vs. Producción real', fontsize=14, fontweight='bold', color='#333333', pad=18)
    ax.set_xticks(x)
    ax.set_xticklabels(months, fontsize=10)
    ax.yaxis.set_major_formatter(plt.FuncFormatter(lambda val, loc: f"{int(val):,}".replace(',', '.')))
    ax.set_ylim(0, 1650000)

    ax.spines['top'].set_visible(False)
    ax.spines['right'].set_visible(False)
    ax.spines['left'].set_color('#CCCCCC')
    ax.spines['bottom'].set_color('#CCCCCC')
    ax.yaxis.grid(True, linestyle='--', alpha=0.4, color='#CCCCCC')
    ax.set_axisbelow(True)

    for i, (p, c) in enumerate(zip(prod, cap)):
        p_str = f"{p:,}".replace(',', '.')
        c_str = f"{c:,}".replace(',', '.')
        ax.text(x[i] - width/2, p + 25000, p_str, ha='center', va='bottom', fontsize=7.5, fontweight='bold', color='#111111')
        ax.text(x[i] + width/2, c + 25000, c_str, ha='center', va='bottom', fontsize=7.5, color='#444444')

    ax.legend(loc='upper center', bbox_to_anchor=(0.5, -0.14), ncol=2, frameon=False, fontsize=9.5)
    plt.tight_layout()
    buf = io.BytesIO()
    plt.savefig(buf, format='png', bbox_inches='tight', dpi=150)
    plt.close()
    buf.seek(0)
    return buf.getvalue()

def generate_sl_units_chart(sep_tot=883422, sep_del=708572, sep_pct=19.79):
    months = ['ene-26', 'feb-26', 'mar-26', 'abr-26', 'may-26', 'jun-26', 'jul-26', 'ago-26', 'sep-26', 'Prom']
    tot = [1180072, 1387220, 1243118, 1095089, 993716, 788553, 932249, 1115859, int(sep_tot)]
    del_u = [59703, 35530, 38754, 5376, 18993, 1200, 17600, 19232, int(sep_del)]
    pct = [94.94, 97.44, 96.88, 99.51, 98.09, 99.85, 98.11, 98.28, float(sep_pct)]

    prom_tot = int(np.mean(tot))
    prom_del = int(np.mean(del_u))
    prom_pct = round(float(np.mean(pct)), 2)
    tot.append(prom_tot)
    del_u.append(prom_del)
    pct.append(prom_pct)

    x = np.arange(len(months))
    width = 0.38

    fig, ax = plt.subplots(figsize=(8.0, 4.2), dpi=150)
    fig.patch.set_facecolor('white')
    ax.set_facecolor('white')

    c_cyan = '#22C5C3'
    c_gray = '#B0B0B0'

    ax.bar(x - width/2, tot, width, label='Total unidades Pedidos Ud/Mes', color=c_cyan)
    ax.bar(x + width/2, del_u, width, label='Total unidades Pedidos Atrasados Ud/Mes', color=c_gray)

    ax.set_title('Nivel de servicio total de planta [Unid.]', fontsize=14, fontweight='bold', color='#333333', pad=18)
    ax.set_xticks(x)
    ax.set_xticklabels(months, fontsize=10)
    ax.yaxis.set_major_formatter(plt.FuncFormatter(lambda val, loc: f"{int(val):,}".replace(',', '.')))
    ax.set_ylim(0, 1650000)

    ax.spines['top'].set_visible(False)
    ax.spines['right'].set_visible(False)
    ax.spines['left'].set_color('#CCCCCC')
    ax.spines['bottom'].set_color('#CCCCCC')
    ax.yaxis.grid(True, linestyle='--', alpha=0.4, color='#CCCCCC')
    ax.set_axisbelow(True)

    for i in range(len(months)):
        t_str = f"{tot[i]:,}".replace(',', '.')
        d_str = f"{del_u[i]:,}".replace(',', '.')
        p_str = f"{pct[i]:.2f}%".replace('.', ',')
        ax.text(x[i] - width/2, tot[i] + 25000, t_str, ha='center', va='bottom', fontsize=7.0, fontweight='bold', color='#111111')
        ax.text(x[i] + width/2, del_u[i] + 25000, d_str, ha='center', va='bottom', fontsize=6.8, color='#444444')
        ax.text(x[i], 180000, p_str, ha='center', va='center', fontsize=7.2, fontweight='bold', color='#0f766e',
                bbox=dict(boxstyle='square,pad=0.2', facecolor='white', edgecolor='#22C5C3', alpha=0.9))

    ax.legend(loc='upper center', bbox_to_anchor=(0.5, -0.14), ncol=2, frameon=False, fontsize=9.5)
    plt.tight_layout()
    buf = io.BytesIO()
    plt.savefig(buf, format='png', bbox_inches='tight', dpi=150)
    plt.close()
    buf.seek(0)
    return buf.getvalue()

def generate_sl_cc_chart(sep_tot=202, sep_del=162, sep_pct=19.80):
    months = ['ene-26', 'feb-26', 'mar-26', 'abr-26', 'may-26', 'jun-26', 'jul-26', 'ago-26', 'sep-26', 'Prom']
    tot = [100, 78, 111, 92, 91, 76, 71, 91, int(sep_tot)]
    del_cc = [22, 20, 8, 5, 6, 2, 7, 6, int(sep_del)]
    pct = [78.0, 74.36, 92.79, 94.57, 93.41, 97.37, 90.14, 93.41, float(sep_pct)]

    prom_tot = int(np.mean(tot))
    prom_del = int(np.mean(del_cc))
    prom_pct = round(float(np.mean(pct)), 2)
    tot.append(prom_tot)
    del_cc.append(prom_del)
    pct.append(prom_pct)

    x = np.arange(len(months))
    width = 0.35

    fig, ax1 = plt.subplots(figsize=(8.0, 4.2), dpi=150)
    fig.patch.set_facecolor('white')
    ax1.set_facecolor('white')

    ax2 = ax1.twinx()

    c_cyan = '#22C5C3'
    c_gray = '#B0B0B0'
    c_orange = '#F59E0B'

    rects1 = ax1.bar(x - width/2, tot, width, label='Total Pedidos  CC/Mes', color=c_cyan)
    rects2 = ax1.bar(x + width/2, del_cc, width, label='Total Pedidos Atrasados CC/Mes', color=c_gray)
    line1 = ax2.plot(x, pct, color=c_orange, linewidth=2.5, marker='o', markersize=4, label='Nivel de Servicio.   %')

    ax1.set_title('Nivel de servicio total de planta [C.C.]', fontsize=14, fontweight='bold', color='#333333', pad=18)
    ax1.set_xticks(x)
    ax1.set_xticklabels(months, fontsize=10)
    ax1.set_ylim(0, 240)
    ax2.set_ylim(0, 120)
    ax2.yaxis.set_major_formatter(plt.FuncFormatter(lambda val, loc: f"{int(val)}%"))

    ax1.spines['top'].set_visible(False)
    ax2.spines['top'].set_visible(False)
    ax1.spines['left'].set_color('#CCCCCC')
    ax1.spines['bottom'].set_color('#CCCCCC')
    ax1.yaxis.grid(True, linestyle='--', alpha=0.4, color='#CCCCCC')
    ax1.set_axisbelow(True)

    for i in range(len(months)):
        ax1.text(x[i] - width/2, tot[i] + 4, str(tot[i]), ha='center', va='bottom', fontsize=7.2, fontweight='bold', color='#111111')
        ax1.text(x[i] + width/2, del_cc[i] + 4, str(del_cc[i]), ha='center', va='bottom', fontsize=7.0, color='#444444')
        ax2.text(x[i], pct[i] - 12, f"{pct[i]:.1f}%", ha='center', va='bottom', fontsize=7.2, fontweight='bold', color='#B45309')

    lines, labels = ax1.get_legend_handles_labels()
    lines2, labels2 = ax2.get_legend_handles_labels()
    ax1.legend(lines + lines2, labels + labels2, loc='upper center', bbox_to_anchor=(0.5, -0.14), ncol=3, frameon=False, fontsize=9.0)

    plt.tight_layout()
    buf = io.BytesIO()
    plt.savefig(buf, format='png', bbox_inches='tight', dpi=150)
    plt.close()
    buf.seek(0)
    return buf.getvalue()

def generate_waste_chart(sep_real=0.68):
    months = ['ene-26', 'feb-26', 'mar-26', 'abr-26', 'may-26', 'jun-26', 'jul-26', 'ago-26', 'sep-26', 'Prom']
    target = [1.60] * len(months)
    real = [1.45, 1.19, 1.27, 1.03, 1.24, 1.16, 1.03, 0.99, float(sep_real)]
    margin = [round(t - r, 2) for t, r in zip(target[:9], real)]

    prom_real = round(float(np.mean(real)), 2)
    prom_margin = round(1.60 - prom_real, 2)
    real.append(prom_real)
    margin.append(prom_margin)

    x = np.arange(len(months))
    width = 0.28

    fig, ax = plt.subplots(figsize=(8.0, 4.2), dpi=150)
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
    ax.set_xticklabels(months, fontsize=10)
    ax.yaxis.set_major_formatter(plt.FuncFormatter(lambda val, loc: f"{val:.2f}%".replace('.', ',')))
    ax.set_ylim(0, 1.95)

    ax.spines['top'].set_visible(False)
    ax.spines['right'].set_visible(False)
    ax.spines['left'].set_color('#CCCCCC')
    ax.spines['bottom'].set_color('#CCCCCC')
    ax.yaxis.grid(True, linestyle='--', alpha=0.4, color='#CCCCCC')
    ax.set_axisbelow(True)

    for i in range(len(months)):
        ax.text(x[i] - width, target[i] + 0.03, "1,60%", ha='center', va='bottom', fontsize=6.8, fontweight='bold', color='#111111')
        ax.text(x[i], real[i] + 0.03, f"{real[i]:.2f}%".replace('.', ','), ha='center', va='bottom', fontsize=6.8, color='#333333')
        ax.text(x[i] + width, margin[i] + 0.03, f"{margin[i]:.2f}%".replace('.', ','), ha='center', va='bottom', fontsize=6.8, fontweight='bold', color='#B45309')

    ax.legend(loc='upper center', bbox_to_anchor=(0.5, -0.14), ncol=3, frameon=False, fontsize=9.5)
    plt.tight_layout()
    buf = io.BytesIO()
    plt.savefig(buf, format='png', bbox_inches='tight', dpi=150)
    plt.close()
    buf.seek(0)
    return buf.getvalue()

def generate_process_balance_chart(title_name, label_prog, label_proc, label_waste, label_rate,
                                   hist_prog, hist_proc, hist_waste,
                                   sep_prog, sep_proc, sep_waste,
                                   y1_max, y2_max, col_month='sep-26'):
    months = ['ene-26', 'feb-26', 'mar-26', 'abr-26', 'may-26', 'jun-26', 'jul-26', 'ago-26', col_month]
    prog = [float(x) for x in hist_prog] + [float(sep_prog)]
    proc = [float(x) for x in hist_proc] + [float(sep_proc)]
    waste = [float(x) for x in hist_waste] + [float(sep_waste)]
    pct = [w / p * 100.0 if p > 0 else 0.0 for w, p in zip(waste, proc)]

    prom_prog = float(np.mean(prog))
    prom_proc = float(np.mean(proc))
    prom_waste = float(np.mean(waste))
    prom_pct = float(np.mean(pct))

    x_months = np.arange(9)
    x_prom = 9.8
    all_x = list(x_months) + [x_prom]
    all_labels = months + ['Prom']

    fig, ax1 = plt.subplots(figsize=(7.8, 4.2), dpi=150)
    fig.patch.set_facecolor('white')
    ax1.set_facecolor('white')

    ax2 = ax1.twinx()

    width = 0.25
    c_cyan = '#22C5C3'
    c_gray = '#B0B0B0'
    c_blue = '#3B82F6'
    c_orange = '#F59E0B'

    b_prog = ax1.bar(x_months - width, prog[:9], width, label=label_prog, color=c_cyan)
    b_proc = ax1.bar(x_months, proc[:9], width, label=label_proc, color=c_gray)
    b_waste = ax1.bar(x_months + width, waste[:9], width, label=label_waste, color=c_blue)

    ax1.bar(x_prom - width, prom_prog, width, color=c_cyan)
    ax1.bar(x_prom, prom_proc, width, color=c_gray)
    ax1.bar(x_prom + width, prom_waste, width, color=c_blue)

    l_rate = ax2.plot(x_months, pct[:9], color=c_orange, linewidth=2.8, label=label_rate)[0]
    ax2.plot([x_prom], [prom_pct], color=c_orange, marker='o', markersize=4.5)

    for i in range(9):
        va_mode = 'bottom' if (i % 2 == 0 or pct[i] > 0.8) else 'top'
        y_offset = (y2_max * 0.04) if va_mode == 'bottom' else -(y2_max * 0.04)
        ax2.text(x_months[i], pct[i] + y_offset, f'{pct[i]:.2f}%'.replace('.', ','), ha='center', va=va_mode, fontsize=7.0, fontweight='bold', color='#111111')
    ax2.text(x_prom, prom_pct + (y2_max * 0.04), f'{prom_pct:.2f}%'.replace('.', ','), ha='center', va='bottom', fontsize=7.2, fontweight='bold', color='#111111')

    ax1.set_title(title_name, fontsize=13, fontweight='bold', color='#333333', pad=14)
    ax1.set_xticks(all_x)
    ax1.set_xticklabels(all_labels, fontsize=9)
    ax1.set_xlim(-0.7, 10.7)
    ax1.set_ylim(0, y1_max)
    ax2.set_ylim(0, y2_max)

    ax1.yaxis.set_major_formatter(plt.FuncFormatter(lambda val, loc: f'{int(val):,}'.replace(',', '.')))
    ax2.yaxis.set_major_formatter(plt.FuncFormatter(lambda val, loc: f'{val:.2f}%'.replace('.', ',')))

    ax1.spines['top'].set_visible(False)
    ax2.spines['top'].set_visible(False)
    ax1.spines['left'].set_color('#CCCCCC')
    ax2.spines['left'].set_color('#CCCCCC')
    ax1.spines['right'].set_color('#CCCCCC')
    ax2.spines['right'].set_color('#CCCCCC')
    ax1.spines['bottom'].set_color('#CCCCCC')
    ax2.spines['bottom'].set_color('#CCCCCC')

    ax1.yaxis.grid(True, linestyle='--', alpha=0.4, color='#CCCCCC')
    ax1.set_axisbelow(True)

    legend_handles = [b_prog, b_waste, b_proc, l_rate]
    legend_labels = [label_prog, label_waste, label_proc, label_rate]
    ax1.legend(legend_handles, legend_labels, loc='upper center', bbox_to_anchor=(0.5, -0.15), ncol=2, frameon=False, fontsize=8.0)

    plt.tight_layout()
    buf = io.BytesIO()
    plt.savefig(buf, format='png', bbox_inches='tight', dpi=150)
    plt.close()
    buf.seek(0)
    return buf.getvalue()

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

def main():
    parser = argparse.ArgumentParser(description="Generador Mensual PPTX Unibag")
    parser.add_argument("--template", default=os.path.join("data", "KP´S OPERACIONES AGOSTO 2026.pptx"))
    parser.add_argument("--month", default="2026-09")
    parser.add_argument("--data", default="")
    parser.add_argument("--output", default=os.path.join("data", "KPIS_OPERACIONES_SEPTIEMBRE_2026.pptx"))
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

    month_str = args.month
    month_name = "SEPTIEMBRE 2026"
    month_col = "sep-26"
    if month_str == "2026-10":
        month_name = "OCTUBRE 2026"
        month_col = "oct-26"
    elif month_str == "2026-08":
        month_name = "AGOSTO 2026"
        month_col = "ago-26"

    print(f"Generando presentacion para: {month_name} ({month_col})")
    prs = Presentation(args.template)

    # 1. Update text titles across all slides
    for slide_idx, slide in enumerate(prs.slides):
        for shape in slide.shapes:
            update_text_in_shape(shape, "AGOSTO 2026", month_name)
            update_text_in_shape(shape, "Agosto 2026", month_name.capitalize())
            update_text_in_shape(shape, "REPORTE DE OPERACIONES AGOSTO 2026", f"REPORTE DE OPERACIONES {month_name}")

    # 2. Extract metrics (Producción se mide por lo que pasó por CORTE Y SELLADO)
    prod_units = float(data.get("prod_units", 903219))
    prod_meta = float(data.get("prod_meta", 1000000))
    prod_cumpl = (prod_units / prod_meta * 100.0) if prod_meta > 0 else 0.0

    # Gestión 2026 acumulados (8 meses anteriores + septiembre)
    prev_prod_total_8m = 8605557
    curr_prod_total_9m = prev_prod_total_8m + prod_units
    curr_prod_prom_9m = curr_prod_total_9m / 9.0
    curr_cumpl_prom_9m = (curr_prod_prom_9m / prod_meta * 100.0)

    # Slide 2: Table Producción (Imagen 6) -> NATIVE EDITABLE TABLE
    prod_rows = [
        ["Meta Planta reflejada en Unidades", "Un/Mes", f"{int(prod_meta):,}".replace(',', '.'), "1.000.000", "12.000.000", "958.333", "11.500.000"],
        ["% cumplimiento Meta", "%", f"{prod_cumpl:.2f}%".replace('.', ','), f"{curr_cumpl_prom_9m:.2f}%".replace('.', ','), f"{curr_cumpl_prom_9m:.2f}%".replace('.', ','), "100,60%", "100,60%"],
        ["Producción Mes", "Und", f"{int(prod_units):,}".replace(',', '.'), f"{int(curr_prod_prom_9m):,}".replace(',', '.'), f"{int(curr_prod_total_9m):,}".replace(',', '.'), "964.880", "11.569.083"]
    ]
    t2_png = draw_kpi_table(col_month=month_col, rows=prod_rows, green_rows=[2], red_cells=[(1, 2), (1, 3), (1, 4), (1, 5), (1, 6)])
    replace_picture_with_native_table(prs.slides[1], 'Imagen 6', month_col, prod_rows, green_rows=[2], red_cells=[(1, 2), (1, 3), (1, 4), (1, 5), (1, 6)], fallback_png=t2_png)

    # Slide 2: Chart Producción (Imagen 9)
    c2_png = generate_prod_chart(sep_prod=prod_units, sep_cap=1345000)
    replace_picture_blob(prs.slides[1], 'Imagen 9', c2_png)

    # Slide 5: Nivel de Servicio [Und.] (Imagen 8: Tabla, Imagen 10: Gráfico)
    sl_total_units = float(data.get("sl_total_units", 883422))
    sl_delayed_units = float(data.get("sl_delayed_units", 708572))
    sl_percent_units = float(data.get("sl_percent_units", 19.79))
    prev_sl_tot_units = 8735876 + sl_total_units
    prev_sl_del_units = 196388 + sl_delayed_units
    curr_sl_prom_units = prev_sl_tot_units / 9.0
    curr_sl_del_prom = prev_sl_del_units / 9.0

    # Slide 5: Table Nivel de Servicio [Und.] (Imagen 8) -> NATIVE EDITABLE TABLE
    sl_u_rows = [
        ["Total unidades Pedidos", "Ud/Mes", f"{int(sl_total_units):,}".replace(',', '.'), f"{int(curr_sl_prom_units):,}".replace(',', '.'), f"{int(prev_sl_tot_units):,}".replace(',', '.'), "971.715", "11.660.579"],
        ["Total unidades Pedidos Atrasados", "Ud/Mes", f"{int(sl_delayed_units):,}".replace(',', '.'), f"{int(curr_sl_del_prom):,}".replace(',', '.'), f"{int(prev_sl_del_units):,}".replace(',', '.'), "16.937", "203.243"],
        ["Nivel de Servicio.", "%", f"{sl_percent_units:.2f}%".replace('.', ','), "88,14%", "88,14%", "98,42%", "1181,01%"]
    ]
    t5_png = draw_kpi_table(col_month=month_col, rows=sl_u_rows, green_rows=[2], red_cells=[(1, 2), (1, 3), (1, 4), (1, 5), (1, 6)])
    replace_picture_with_native_table(prs.slides[4], 'Imagen 8', month_col, sl_u_rows, green_rows=[2], red_cells=[(1, 2), (1, 3), (1, 4), (1, 5), (1, 6)], fallback_png=t5_png)

    c5_png = generate_sl_units_chart(sep_tot=sl_total_units, sep_del=sl_delayed_units, sep_pct=sl_percent_units)
    replace_picture_blob(prs.slides[4], 'Imagen 10', c5_png)

    # Slide 6: Nivel de Servicio [C.C.] (Imagen 9: Tabla, Imagen 11: Gráfico)
    sl_total_cc = int(data.get("sl_total_cc", 202))
    sl_delayed_cc = int(data.get("sl_delayed_cc", 162))
    sl_percent_cc = float(data.get("sl_percent_cc", 19.80))
    # Slide 6: Nivel de Servicio [C.C.] (Imagen 9) -> NATIVE EDITABLE TABLE
    sl_cc_rows = [
        ["Total Pedidos", "CC/Mes", str(sl_total_cc), "101", f"{710 + sl_total_cc}", "78", "940"],
        ["Total Pedidos Atrasados", "CC/Mes", str(sl_delayed_cc), "26", f"{76 + sl_delayed_cc}", "5", "54"],
        ["Nivel de Servicio.", "%", f"{sl_percent_cc:.2f}%".replace('.', ','), "81,50%", "81,50%", "94,85%", "94,26%"]
    ]
    t6_png = draw_kpi_table(col_month=month_col, rows=sl_cc_rows, green_rows=[2], red_cells=[(1, 2), (1, 3), (1, 4), (1, 5), (1, 6)])
    replace_picture_with_native_table(prs.slides[5], 'Imagen 9', month_col, sl_cc_rows, green_rows=[2], red_cells=[(1, 2), (1, 3), (1, 4), (1, 5), (1, 6)], fallback_png=t6_png)

    c6_png = generate_sl_cc_chart(sep_tot=sl_total_cc, sep_del=sl_delayed_cc, sep_pct=sl_percent_cc)
    replace_picture_blob(prs.slides[5], 'Imagen 11', c6_png)

    # Slide 7: Detalle Atrasados (Imagen 7) -> NATIVE EDITABLE TABLE
    delayed_orders = data.get("delayed_orders", [])
    t7_png = draw_delayed_orders_table(delayed_orders)
    replace_picture_with_delayed_table(prs.slides[6], 'Imagen 7', delayed_orders, fallback_png=t7_png)

    # Slide 8: Mermas Flexo (Imagen 9: Tabla, Imagen 12: Gráfico) -> NATIVE EDITABLE TABLE
    flexo_prog = float(data.get("flexo_prog_kg", 45200))
    flexo_proc = float(data.get("flexo_proc_kg", 44850))
    flexo_waste = float(data.get("flexo_waste_kg", 412))
    flexo_rate = (flexo_waste / flexo_proc * 100.0) if flexo_proc > 0 else 0.92
    flexo_rows = [
        ["Kgs programados en Impresión (Flexos)", "kgs", f"{int(flexo_prog):,}".replace(',', '.'), "44.882", "403.977", "38.423", "461.073"],
        ["Kgs procesados en Impresión (Flexos)", "kgs", f"{int(flexo_proc):,}".replace(',', '.'), "44.918", "404.258", "38.372", "460.460"],
        ["Kgs Merma en Flexos", "kgs", f"{int(flexo_waste):,}".replace(',', '.'), "905", "8.141", "264", "3.172"],
        ["% de merma en Flexos", "%", f"{flexo_rate:.2f}%".replace('.', ','), "2,01%", "18,08%", "0,70%", "8,37%"]
    ]
    t8_png = draw_kpi_table(col_month=month_col, rows=flexo_rows, red_cells=[(3, 2), (3, 3), (3, 4), (3, 5), (3, 6)])
    replace_picture_with_native_table(prs.slides[7], 'Imagen 9', month_col, flexo_rows, red_cells=[(3, 2), (3, 3), (3, 4), (3, 5), (3, 6)], fallback_png=t8_png)
    c8_png = generate_process_balance_chart(
        title_name='Balance de masa [Flexografia]',
        label_prog='Kgs programados en Impresión (Flexos) kgs',
        label_proc='Kgs procesados en Impresión (Flexos) kgs',
        label_waste='Kgs Merma en Flexos kgs',
        label_rate='% de merma en Flexos %',
        hist_prog=[49300, 63100, 53600, 46200, 40300, 29500, 36500, 41385],
        hist_proc=[49500, 63300, 53400, 46500, 40600, 29500, 37200, 40424],
        hist_waste=[520, 538, 523, 407, 396, 298, 331, 355],
        sep_prog=flexo_prog, sep_proc=flexo_proc, sep_waste=flexo_waste,
        y1_max=70000, y2_max=1.35, col_month=month_col
    )
    replace_picture_blob(prs.slides[7], 'Imagen 12', c8_png)

    # Slide 9: Mermas Seri (Imagen 9: Tabla, Imagen 12: Gráfico) -> NATIVE EDITABLE TABLE
    seri_prog = float(data.get("seri_prog_kg", 3200))
    seri_proc = float(data.get("seri_proc_kg", 3150))
    seri_waste = float(data.get("seri_waste_kg", 18.5))
    seri_rate = (seri_waste / seri_proc * 100.0) if seri_proc > 0 else 0.58
    seri_rows = [
        ["Kgs programados en Impresión (Seris)", "kgs", f"{int(seri_prog):,}".replace(',', '.'), "2.806", "25.259", "3.216", "38.597"],
        ["Kgs procesados en Impresión (Seris)", "kgs", f"{int(seri_proc):,}".replace(',', '.'), "2.545", "22.907", "3.138", "37.660"],
        ["Kgs Merma en Seris", "kgs", f"{seri_waste:.1f}".replace('.', ','), "16,2", "143,5", "19", "229"],
        ["% de merma en Seris", "%", f"{seri_rate:.2f}%".replace('.', ','), "0,65%", "5,44%", "0,69%", "8,26%"]
    ]
    t9_png = draw_kpi_table(col_month=month_col, rows=seri_rows, red_cells=[(3, 2), (3, 3), (3, 4), (3, 5), (3, 6)])
    replace_picture_with_native_table(prs.slides[8], 'Imagen 9', month_col, seri_rows, red_cells=[(3, 2), (3, 3), (3, 4), (3, 5), (3, 6)], fallback_png=t9_png)
    c9_png = generate_process_balance_chart(
        title_name='Balance de masa [Serigrafia]',
        label_prog='Kgs programados en Impresión (Seris) kgs',
        label_proc='Kgs procesados en Impresión (Seris) kgs',
        label_waste='Kgs Merma en Seris kgs',
        label_rate='% de merma en Seris %',
        hist_prog=[8650, 620, 2800, 750, 1380, 1370, 2090, 4579],
        hist_proc=[4750, 1640, 2900, 3200, 1420, 1380, 2270, 2374],
        hist_waste=[23.2, 11.0, 23.8, 31.4, 12.9, 10.2, 10.4, 9.0],
        sep_prog=seri_prog, sep_proc=seri_proc, sep_waste=seri_waste,
        y1_max=10000, y2_max=1.35, col_month=month_col
    )
    replace_picture_blob(prs.slides[8], 'Imagen 12', c9_png)

    # Slide 10: Mermas Corte y Sellado (Imagen 9: Tabla, Imagen 12: Gráfico) -> NATIVE EDITABLE TABLE
    cys_prog = float(data.get("cys_prog_kg", 52100))
    cys_proc = float(data.get("cys_proc_kg", 52350))
    cys_waste = float(data.get("cys_waste_kg", 118))
    cys_rate = (cys_waste / cys_proc * 100.0) if cys_proc > 0 else 0.23
    cys_rows = [
        ["Kgs programados en Corte y Sellado", "kgs", f"{int(cys_prog):,}".replace(',', '.'), "50.313", "452.821", "44.025", "528.294"],
        ["Kgs procesados en Corte y Sellado", "kgs", f"{int(cys_proc):,}".replace(',', '.'), "51.027", "459.243", "44.528", "534.336"],
        ["Kgs Merma en Cortadoras", "kgs", f"{int(cys_waste):,}".replace(',', '.'), "122", "1.098", "123", "1.474"],
        ["% de merma en Corte y Sellado", "%", f"{cys_rate:.2f}%".replace('.', ','), "0,24%", "1,95%", "0,28%", "3,32%"]
    ]
    t10_png = draw_kpi_table(col_month=month_col, rows=cys_rows, red_cells=[(3, 2), (3, 3), (3, 4), (3, 5), (3, 6)])
    replace_picture_with_native_table(prs.slides[9], 'Imagen 9', month_col, cys_rows, red_cells=[(3, 2), (3, 3), (3, 4), (3, 5), (3, 6)], fallback_png=t10_png)
    c10_png = generate_process_balance_chart(
        title_name='Balance de masa [Corte y Sellado]',
        label_prog='Kgs programados en Corte y Sellado kgs',
        label_proc='Kgs procesados en Corte y Sellado kgs',
        label_waste='Kgs Merma en Cortadoras kgs',
        label_rate='% de merma en Corte y Sellado %',
        hist_prog=[53800, 64500, 57800, 52800, 44500, 34800, 42500, 51214],
        hist_proc=[55000, 65500, 60000, 52800, 45000, 35500, 43200, 51514],
        hist_waste=[132, 170, 168, 95, 108, 89, 108, 126],
        sep_prog=cys_prog, sep_proc=cys_proc, sep_waste=cys_waste,
        y1_max=70000, y2_max=0.35, col_month=month_col
    )
    replace_picture_blob(prs.slides[9], 'Imagen 12', c10_png)

    # Slide 11: Mermas Embalaje (Imagen 9: Tabla, Imagen 12: Gráfico) -> NATIVE EDITABLE TABLE
    emb_prog = float(data.get("emb_prog_kg", 52400))
    emb_proc = float(data.get("emb_proc_kg", 52600))
    emb_waste = float(data.get("emb_waste_kg", 6.2))
    emb_rate = (emb_waste / emb_proc * 100.0) if emb_proc > 0 else 0.01
    emb_rows = [
        ["Kgs programados en Embalaje", "kgs", f"{int(emb_prog):,}".replace(',', '.'), "51.052", "459.472", "43.587", "523.047"],
        ["Kgs procesados en Embalaje", "kgs", f"{int(emb_proc):,}".replace(',', '.'), "51.093", "459.843", "43.661", "523.926"],
        ["Kgs Merma Embalaje", "kgs", f"{emb_waste:.1f}".replace('.', ','), "37", "337", "115", "1.385"],
        ["% de merma Embalaje", "%", f"{emb_rate:.2f}%".replace('.', ','), "0,07%", "0,61%", "0,27%", "3,24%"]
    ]
    t11_png = draw_kpi_table(col_month=month_col, rows=emb_rows, red_cells=[(3, 2), (3, 3), (3, 4), (3, 5), (3, 6)])
    replace_picture_with_native_table(prs.slides[10], 'Imagen 9', month_col, emb_rows, red_cells=[(3, 2), (3, 3), (3, 4), (3, 5), (3, 6)], fallback_png=t11_png)
    c11_png = generate_process_balance_chart(
        title_name='Balance de masa [Embalaje]',
        label_prog='Kgs programados en Embalaje kgs',
        label_proc='Kgs procesados en Embalaje kgs',
        label_waste='Kgs Merma Embalaje kgs',
        label_rate='% de merma Embalaje %',
        hist_prog=[57800, 61500, 59500, 51200, 47000, 36200, 43200, 51530],
        hist_proc=[55000, 62000, 60200, 51200, 47800, 36500, 43500, 51795],
        hist_waste=[99, 74, 48, 36, 48, 15, 4, 7],
        sep_prog=emb_prog, sep_proc=emb_proc, sep_waste=emb_waste,
        y1_max=70000, y2_max=0.22, col_month=month_col
    )
    replace_picture_blob(prs.slides[10], 'Imagen 12', c11_png)

    # Slide 12: Total de Merma (Imagen 9: Gráfico)
    global_waste = float(data.get("global_waste_percent", 0.68))
    c12_png = generate_waste_chart(sep_real=global_waste)
    replace_picture_blob(prs.slides[11], 'Imagen 9', c12_png)

    # Conservar únicamente las diapositivas que fueron modificadas (10 diapositivas clave de operaciones)
    if not args.all_slides:
        keep_indices = {0, 1, 4, 5, 6, 7, 8, 9, 10, 11}
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
