#!/usr/bin/env python3
"""Generate ERD PNG for Aplikasi Simpan Pinjam KOPPUJA."""

from __future__ import annotations

from pathlib import Path

import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, Rectangle
import matplotlib.patheffects as pe

OUT_DIR = Path(__file__).resolve().parent
PNG_PATH = OUT_DIR / "ERD_SimpanPinjam_KOPPUJA.png"

NAVY = "#1B3A5F"
GOLD = "#C9A227"
BG = "#F4F7FB"
INK = "#1F2937"
MUTED = "#64748B"
WHITE = "#FFFFFF"
PK_BG = "#FEF3C7"
FK_BG = "#E0F2FE"
LINE = "#334155"
DASH = "#64748B"

# (name, dtype, kind) kind: PK | FK | UK | ""
ENTITIES = {
    "divisions": {
        "title": "divisions",
        "subtitle": "Divisi / Departemen",
        "color": "#1B3A5F",
        "attrs": [
            ("id", "INT", "PK"),
            ("name", "VARCHAR(100)", ""),
            ("code", "VARCHAR(20)", "UK"),
            ("created_at", "TIMESTAMP", ""),
        ],
    },
    "users": {
        "title": "users",
        "subtitle": "Karyawan / Admin / Nasabah",
        "color": "#1B3A5F",
        "attrs": [
            ("id", "INT", "PK"),
            ("nik", "VARCHAR(20)", "UK"),
            ("full_name", "VARCHAR(100)", ""),
            ("email", "VARCHAR(100)", "UK"),
            ("phone", "VARCHAR(15)", ""),
            ("password", "VARCHAR(255)", ""),
            ("role", "ENUM", ""),
            ("division_id", "INT", "FK"),
            ("position", "VARCHAR(50)", ""),
            ("member_category", "ENUM", ""),
            ("payroll_deduction", "TINYINT", ""),
            ("employment_date", "DATE", ""),
            ("monthly_salary", "DECIMAL(15,2)", ""),
            ("status", "ENUM", ""),
            ("profile_photo", "VARCHAR(255)", ""),
            ("address", "TEXT", ""),
            ("is_verified", "BOOLEAN", ""),
            ("last_login", "TIMESTAMP", ""),
            ("created_at", "TIMESTAMP", ""),
            ("updated_at", "TIMESTAMP", ""),
        ],
    },
    "savings_accounts": {
        "title": "savings_accounts",
        "subtitle": "Akun Tabungan / Simpanan",
        "color": "#0F766E",
        "attrs": [
            ("id", "INT", "PK"),
            ("user_id", "INT", "FK"),
            ("account_number", "VARCHAR(20)", "UK"),
            ("balance", "DECIMAL(15,2)", ""),
            ("interest_rate", "DECIMAL(5,2)", ""),
            ("status", "ENUM", ""),
            ("opened_date", "DATE", ""),
            ("last_transaction_date", "TIMESTAMP", ""),
            ("created_at", "TIMESTAMP", ""),
            ("updated_at", "TIMESTAMP", ""),
        ],
    },
    "loan_applications": {
        "title": "loan_applications",
        "subtitle": "Pengajuan Pinjaman",
        "color": "#B45309",
        "attrs": [
            ("id", "INT", "PK"),
            ("user_id", "INT", "FK"),
            ("application_number", "VARCHAR(30)", "UK"),
            ("loan_amount", "DECIMAL(15,2)", ""),
            ("interest_rate", "DECIMAL(5,2)", ""),
            ("loan_term_months", "INT", ""),
            ("monthly_installment", "DECIMAL(15,2)", ""),
            ("total_payment", "DECIMAL(15,2)", ""),
            ("purpose", "VARCHAR(100)", ""),
            ("description", "TEXT", ""),
            ("collateral", "TEXT", ""),
            ("status", "ENUM", ""),
            ("reviewed_by", "INT", "FK"),
            ("reviewed_at", "TIMESTAMP", ""),
            ("review_notes", "TEXT", ""),
            ("approved_by", "INT", "FK"),
            ("approved_at", "TIMESTAMP", ""),
            ("disbursed_at", "TIMESTAMP", ""),
            ("rejected_reason", "TEXT", ""),
            ("documents", "JSON", ""),
            ("emergency_contact_name", "VARCHAR(100)", ""),
            ("emergency_contact_phone", "VARCHAR(15)", ""),
            ("emergency_contact_relation", "VARCHAR(50)", ""),
            ("created_at", "TIMESTAMP", ""),
            ("updated_at", "TIMESTAMP", ""),
        ],
    },
    "active_loans": {
        "title": "active_loans",
        "subtitle": "Pinjaman Aktif",
        "color": "#B45309",
        "attrs": [
            ("id", "INT", "PK"),
            ("user_id", "INT", "FK"),
            ("application_id", "INT", "FK"),
            ("loan_number", "VARCHAR(30)", "UK"),
            ("original_amount", "DECIMAL(15,2)", ""),
            ("remaining_balance", "DECIMAL(15,2)", ""),
            ("monthly_installment", "DECIMAL(15,2)", ""),
            ("interest_rate", "DECIMAL(5,2)", ""),
            ("loan_term_months", "INT", ""),
            ("remaining_months", "INT", ""),
            ("start_date", "DATE", ""),
            ("next_due_date", "DATE", ""),
            ("status", "ENUM", ""),
            ("total_paid", "DECIMAL(15,2)", ""),
            ("late_fees", "DECIMAL(15,2)", ""),
            ("created_at", "TIMESTAMP", ""),
            ("updated_at", "TIMESTAMP", ""),
        ],
    },
    "loan_schedules": {
        "title": "loan_schedules",
        "subtitle": "Jadwal Angsuran",
        "color": "#B45309",
        "attrs": [
            ("id", "INT", "PK"),
            ("loan_id", "INT", "FK"),
            ("installment_number", "INT", ""),
            ("due_date", "DATE", ""),
            ("principal_amount", "DECIMAL(15,2)", ""),
            ("interest_amount", "DECIMAL(15,2)", ""),
            ("total_amount", "DECIMAL(15,2)", ""),
            ("paid_amount", "DECIMAL(15,2)", ""),
            ("paid_date", "DATE", ""),
            ("late_fee", "DECIMAL(15,2)", ""),
            ("status", "ENUM", ""),
            ("payment_transaction_id", "INT", "FK"),
            ("created_at", "TIMESTAMP", ""),
        ],
    },
    "transactions": {
        "title": "transactions",
        "subtitle": "Transaksi Keuangan",
        "color": "#3730A3",
        "attrs": [
            ("id", "INT", "PK"),
            ("user_id", "INT", "FK"),
            ("reference_number", "VARCHAR(30)", "UK"),
            ("type", "ENUM", ""),
            ("amount", "DECIMAL(15,2)", ""),
            ("description", "TEXT", ""),
            ("related_account_id", "INT", "FK"),
            ("related_loan_id", "INT", "FK"),
            ("balance_before", "DECIMAL(15,2)", ""),
            ("balance_after", "DECIMAL(15,2)", ""),
            ("payment_method", "ENUM", ""),
            ("payment_reference", "VARCHAR(100)", ""),
            ("processed_by", "INT", "FK"),
            ("status", "ENUM", ""),
            ("transaction_date", "DATE", ""),
            ("created_at", "TIMESTAMP", ""),
        ],
    },
    "notifications": {
        "title": "notifications",
        "subtitle": "Notifikasi Pengguna",
        "color": "#475569",
        "attrs": [
            ("id", "INT", "PK"),
            ("user_id", "INT", "FK"),
            ("title", "VARCHAR(200)", ""),
            ("message", "TEXT", ""),
            ("type", "ENUM", ""),
            ("category", "ENUM", ""),
            ("is_read", "BOOLEAN", ""),
            ("action_url", "VARCHAR(255)", ""),
            ("expires_at", "TIMESTAMP", ""),
            ("created_at", "TIMESTAMP", ""),
        ],
    },
    "activity_logs": {
        "title": "activity_logs",
        "subtitle": "Log Aktivitas",
        "color": "#475569",
        "attrs": [
            ("id", "INT", "PK"),
            ("user_id", "INT", "FK"),
            ("action", "VARCHAR(100)", ""),
            ("entity_type", "VARCHAR(50)", ""),
            ("entity_id", "INT", ""),
            ("old_values", "JSON", ""),
            ("new_values", "JSON", ""),
            ("ip_address", "VARCHAR(45)", ""),
            ("user_agent", "TEXT", ""),
            ("created_at", "TIMESTAMP", ""),
        ],
    },
    "system_settings": {
        "title": "system_settings",
        "subtitle": "Pengaturan Sistem",
        "color": "#334155",
        "attrs": [
            ("id", "INT", "PK"),
            ("setting_key", "VARCHAR(100)", "UK"),
            ("setting_value", "TEXT", ""),
            ("setting_type", "ENUM", ""),
            ("description", "TEXT", ""),
            ("updated_by", "INT", "FK"),
            ("updated_at", "TIMESTAMP", ""),
        ],
    },
    "otp_verifications": {
        "title": "otp_verifications",
        "subtitle": "Kode OTP",
        "color": "#6D28D9",
        "attrs": [
            ("id", "INT", "PK"),
            ("user_id", "INT", "FK"),
            ("otp_code", "VARCHAR(6)", ""),
            ("purpose", "ENUM", ""),
            ("phone_number", "VARCHAR(15)", ""),
            ("is_used", "BOOLEAN", ""),
            ("expires_at", "TIMESTAMP", ""),
            ("created_at", "TIMESTAMP", ""),
        ],
    },
    "password_reset_tokens": {
        "title": "password_reset_tokens",
        "subtitle": "Token Reset Password",
        "color": "#6D28D9",
        "attrs": [
            ("id", "INT", "PK"),
            ("user_id", "INT", "FK"),
            ("token", "VARCHAR(64)", ""),
            ("is_used", "BOOLEAN", ""),
            ("expires_at", "TIMESTAMP", ""),
            ("created_at", "TIMESTAMP", ""),
        ],
    },
    "shu_periods": {
        "title": "shu_periods",
        "subtitle": "Periode Perhitungan SHU",
        "color": "#047857",
        "attrs": [
            ("id", "INT", "PK"),
            ("year", "INT", "UK"),
            ("start_date", "DATE", ""),
            ("end_date", "DATE", ""),
            ("operating_expense", "DECIMAL(15,2)", ""),
            ("other_income", "DECIMAL(15,2)", ""),
            ("interest_income", "DECIMAL(15,2)", ""),
            ("late_fee_income", "DECIMAL(15,2)", ""),
            ("total_income", "DECIMAL(15,2)", ""),
            ("total_expense", "DECIMAL(15,2)", ""),
            ("shu_amount", "DECIMAL(15,2)", ""),
            ("pct_cadangan", "DECIMAL(5,2)", ""),
            ("pct_jasa_anggota", "DECIMAL(5,2)", ""),
            ("pct_jasa_simpanan", "DECIMAL(5,2)", ""),
            ("pct_jasa_pinjaman", "DECIMAL(5,2)", ""),
            ("pct_dana_pengurus", "DECIMAL(5,2)", ""),
            ("pct_dana_karyawan", "DECIMAL(5,2)", ""),
            ("pct_dana_pendidikan", "DECIMAL(5,2)", ""),
            ("pct_dana_sosial", "DECIMAL(5,2)", ""),
            ("amt_cadangan", "DECIMAL(15,2)", ""),
            ("amt_jasa_anggota", "DECIMAL(15,2)", ""),
            ("amt_jasa_simpanan", "DECIMAL(15,2)", ""),
            ("amt_jasa_pinjaman", "DECIMAL(15,2)", ""),
            ("amt_dana_pengurus", "DECIMAL(15,2)", ""),
            ("amt_dana_karyawan", "DECIMAL(15,2)", ""),
            ("amt_dana_pendidikan", "DECIMAL(15,2)", ""),
            ("amt_dana_sosial", "DECIMAL(15,2)", ""),
            ("total_savings_base", "DECIMAL(15,2)", ""),
            ("total_interest_base", "DECIMAL(15,2)", ""),
            ("member_count", "INT", ""),
            ("notes", "TEXT", ""),
            ("status", "ENUM", ""),
            ("generated_by", "INT", "FK"),
            ("generated_at", "TIMESTAMP", ""),
            ("updated_at", "TIMESTAMP", ""),
        ],
    },
    "shu_member_shares": {
        "title": "shu_member_shares",
        "subtitle": "Bagian SHU per Anggota",
        "color": "#047857",
        "attrs": [
            ("id", "INT", "PK"),
            ("period_id", "INT", "FK"),
            ("user_id", "INT", "FK"),
            ("savings_balance", "DECIMAL(15,2)", ""),
            ("interest_paid", "DECIMAL(15,2)", ""),
            ("late_fee_paid", "DECIMAL(15,2)", ""),
            ("shu_simpanan", "DECIMAL(15,2)", ""),
            ("shu_pinjaman", "DECIMAL(15,2)", ""),
            ("shu_total", "DECIMAL(15,2)", ""),
        ],
    },
    "rules": {
        "title": "rules",
        "subtitle": "Aturan Keputusan (THEN)",
        "color": "#9F1239",
        "attrs": [
            ("id", "INT", "PK"),
            ("rule_code", "VARCHAR(20)", "UK"),
            ("rule_group", "ENUM", ""),
            ("name", "VARCHAR(150)", ""),
            ("description", "TEXT", ""),
            ("conclusion", "ENUM", ""),
            ("message", "TEXT", ""),
            ("priority", "INT", ""),
            ("is_active", "BOOLEAN", ""),
            ("is_system", "BOOLEAN", ""),
            ("created_by", "INT", "FK"),
            ("updated_by", "INT", "FK"),
            ("created_at", "TIMESTAMP", ""),
            ("updated_at", "TIMESTAMP", ""),
        ],
    },
    "rule_conditions": {
        "title": "rule_conditions",
        "subtitle": "Kondisi Aturan (IF)",
        "color": "#9F1239",
        "attrs": [
            ("id", "INT", "PK"),
            ("rule_id", "INT", "FK"),
            ("fact_key", "VARCHAR(50)", ""),
            ("operator", "ENUM", ""),
            ("value_type", "ENUM", ""),
            ("compare_value", "VARCHAR(255)", ""),
            ("fallback_value", "VARCHAR(255)", ""),
            ("sort_order", "INT", ""),
            ("created_at", "TIMESTAMP", ""),
        ],
    },
    "rule_evaluation_logs": {
        "title": "rule_evaluation_logs",
        "subtitle": "Jejak Inferensi Aturan",
        "color": "#9F1239",
        "attrs": [
            ("id", "INT", "PK"),
            ("user_id", "INT", "FK"),
            ("rule_group", "VARCHAR(50)", ""),
            ("entity_type", "VARCHAR(50)", ""),
            ("entity_id", "INT", ""),
            ("decision", "VARCHAR(30)", ""),
            ("fired_rule_code", "VARCHAR(20)", ""),
            ("facts", "JSON", ""),
            ("trace", "JSON", ""),
            ("created_at", "TIMESTAMP", ""),
        ],
    },
}

COLUMNS = [
    ["divisions", "users", "otp_verifications", "password_reset_tokens"],
    ["savings_accounts", "transactions", "notifications", "activity_logs", "system_settings"],
    ["loan_applications", "active_loans", "loan_schedules"],
    ["shu_periods", "shu_member_shares"],
    ["rules", "rule_conditions", "rule_evaluation_logs"],
]

COLUMN_LABELS = [
    "Organisasi & Keamanan",
    "Simpanan, Transaksi & Sistem",
    "Pinjaman",
    "Sisa Hasil Usaha (SHU)",
    "Rule Engine",
]

BOX_W = 3.62
HEADER_H = 0.48
ROW_H = 0.168
COL_GAP = 1.38
ROW_GAP = 0.38
LEFT = 0.42
GROUP_H = 0.34
LEGEND_H = 1.48

# Primary business FKs only. Audit FKs (reviewed_by, processed_by, generated_by, created_by)
# remain visible as FK attributes inside the entity boxes.
# src, dst, src_side, dst_side, src_frac, dst_frac, card_src, card_dst, dashed, label, highway
# highway: None | "top" | "bottom"  (for non-adjacent columns)
RELS = [
    ("divisions", "users", "bottom", "top", 0.50, 0.50, "1", "0..N", False, "berisi", None),
    ("users", "otp_verifications", "bottom", "top", 0.35, 0.50, "1", "N", False, "memverifikasi", None),
    ("users", "password_reset_tokens", "left", "left", 0.88, 0.45, "1", "N", False, "mereset", None),
    ("users", "savings_accounts", "right", "left", 0.22, 0.18, "1", "N", False, "memiliki", None),
    ("users", "transactions", "right", "left", 0.48, 0.16, "1", "N", False, "melakukan", None),
    ("users", "notifications", "right", "left", 0.70, 0.18, "1", "N", False, "menerima", None),
    ("users", "activity_logs", "right", "left", 0.82, 0.18, "1", "N", False, "mencatat", None),
    ("users", "system_settings", "right", "left", 0.94, 0.40, "1", "0..N", True, "mengubah", None),
    ("savings_accounts", "transactions", "bottom", "top", 0.50, 0.50, "1", "0..N", False, "terkait rekening", None),
    ("users", "loan_applications", "right", "left", 0.12, 0.10, "1", "N", False, "mengajukan", "top"),
    ("loan_applications", "active_loans", "bottom", "top", 0.50, 0.50, "1", "0..1", False, "dicairkan menjadi", None),
    ("users", "active_loans", "right", "left", 0.36, 0.14, "1", "N", False, "memegang", "top"),
    ("active_loans", "loan_schedules", "bottom", "top", 0.50, 0.50, "1", "N", False, "dijadwalkan", None),
    ("active_loans", "transactions", "left", "right", 0.42, 0.55, "1", "0..N", False, "terkait pinjaman", None),
    ("transactions", "loan_schedules", "right", "left", 0.72, 0.82, "1", "0..1", True, "membayar angsuran", None),
    ("shu_periods", "shu_member_shares", "bottom", "top", 0.50, 0.50, "1", "N", False, "dibagikan ke", None),
    ("users", "shu_member_shares", "right", "left", 0.58, 0.28, "1", "N", False, "menerima SHU", "bottom"),
    ("users", "shu_periods", "right", "left", 0.06, 0.06, "1", "0..N", True, "menghasilkan", "top"),
    ("rules", "rule_conditions", "bottom", "top", 0.50, 0.50, "1", "N", False, "memiliki syarat", None),
    ("users", "rule_evaluation_logs", "right", "left", 0.90, 0.45, "1", "0..N", False, "dievaluasi", "bottom"),
    ("users", "rules", "right", "left", 0.28, 0.78, "1", "0..N", True, "menyusun", "top"),
]


def box_height(key: str) -> float:
    n = len(ENTITIES[key]["attrs"])
    return HEADER_H + n * ROW_H + 0.04


def column_stack_height(col: list[str]) -> float:
    return sum(box_height(k) for k in col) + ROW_GAP * (len(col) - 1)


def col_index(key: str) -> int:
    for i, col in enumerate(COLUMNS):
        if key in col:
            return i
    raise KeyError(key)


def gutter_x(between_left_col: int) -> float:
    """X of the vertical gutter to the right of column `between_left_col`."""
    return LEFT + (between_left_col + 1) * BOX_W + between_left_col * COL_GAP + COL_GAP / 2


def layout_boxes(box_top: float) -> dict[str, tuple[float, float, float, float]]:
    boxes = {}
    x = LEFT
    for col in COLUMNS:
        y = box_top
        for key in col:
            h = box_height(key)
            boxes[key] = (x, y - h, BOX_W, h)
            y = y - h - ROW_GAP
        x += BOX_W + COL_GAP
    return boxes


def side_point(box, side: str, frac: float) -> tuple[float, float]:
    x, y, w, h = box
    frac = min(max(frac, 0.06), 0.94)
    if side == "left":
        return x, y + h * (1 - frac)
    if side == "right":
        return x + w, y + h * (1 - frac)
    if side == "top":
        return x + w * frac, y + h
    if side == "bottom":
        return x + w * frac, y
    raise ValueError(side)


def stub(pt, side, pad=0.20):
    x, y = pt
    if side == "right":
        return x + pad, y
    if side == "left":
        return x - pad, y
    if side == "top":
        return x, y + pad
    return x, y - pad


def orthogonal_path(p1, side1, p2, side2, src_key, dst_key, highway, box_top, box_bottom):
    a = stub(p1, side1)
    b = stub(p2, side2)
    c1, c2 = col_index(src_key), col_index(dst_key)

    if highway == "top":
        hy = box_top + 0.10 + abs(c1 - c2) * 0.07
        if c1 < c2:
            gx1 = gutter_x(c1)
            gx2 = gutter_x(c2 - 1)
        else:
            gx1 = gutter_x(c1 - 1)
            gx2 = gutter_x(c2)
        return [p1, a, (a[0], hy), (b[0], hy), b, p2] if abs(c1 - c2) == 1 else [
            p1, a, (gx1, a[1]), (gx1, hy), (gx2, hy), (gx2, b[1]), b, p2
        ]

    if highway == "bottom":
        hy = box_bottom - 0.18 - abs(c1 - c2) * 0.08
        if c1 < c2:
            gx1 = gutter_x(c1)
            gx2 = gutter_x(c2 - 1)
        else:
            gx1 = gutter_x(c1 - 1)
            gx2 = gutter_x(c2)
        return [p1, a, (gx1, a[1]), (gx1, hy), (gx2, hy), (gx2, b[1]), b, p2]

    # Same column: stay in the side gutter then vertical
    if c1 == c2:
        gx = LEFT + c1 * (BOX_W + COL_GAP) - 0.22
        if side1 in ("top", "bottom") and side2 in ("top", "bottom"):
            return [p1, a, b, p2]
        return [p1, a, (gx, a[1]), (gx, b[1]), b, p2]

    # Adjacent columns: shared gutter
    left_col = min(c1, c2)
    gx = gutter_x(left_col)
    if side1 in ("left", "right") and side2 in ("left", "right"):
        return [p1, a, (gx, a[1]), (gx, b[1]), b, p2]
    if side1 in ("top", "bottom") and side2 in ("top", "bottom"):
        mid_y = (a[1] + b[1]) / 2
        return [p1, a, (a[0], mid_y), (b[0], mid_y), b, p2]
    if side1 in ("left", "right"):
        return [p1, a, (gx, a[1]), (gx, b[1]), b, p2]
    return [p1, a, (gx, a[1]), (gx, b[1]), b, p2]


def draw_crows_foot(ax, tip, direction, many=True, optional=False):
    """Draw crow's foot / one marker at relationship end. tip is on the entity edge."""
    x, y = tip
    s = 0.11
    if direction == "left":
        dx, dy = -1, 0
    elif direction == "right":
        dx, dy = 1, 0
    elif direction == "top":
        dx, dy = 0, 1
    else:
        dx, dy = 0, -1
    px, py = -dy, dx  # perpendicular

    base_x = x + dx * s * 1.15
    base_y = y + dy * s * 1.15

    if many:
        ax.plot([x, base_x + px * s], [y, base_y + py * s], color=LINE, lw=1.35, zorder=6, solid_capstyle="round")
        ax.plot([x, base_x], [y, base_y], color=LINE, lw=1.35, zorder=6, solid_capstyle="round")
        ax.plot([x, base_x - px * s], [y, base_y - py * s], color=LINE, lw=1.35, zorder=6, solid_capstyle="round")
    else:
        # two bars for "one"
        b1x = x + dx * s * 0.35
        b1y = y + dy * s * 0.35
        b2x = x + dx * s * 0.70
        b2y = y + dy * s * 0.70
        ax.plot(
            [b1x + px * s * 0.85, b1x - px * s * 0.85],
            [b1y + py * s * 0.85, b1y - py * s * 0.85],
            color=LINE, lw=1.5, zorder=6,
        )
        ax.plot(
            [b2x + px * s * 0.85, b2x - px * s * 0.85],
            [b2y + py * s * 0.85, b2y - py * s * 0.85],
            color=LINE, lw=1.5, zorder=6,
        )

    if optional:
        cx = x + dx * s * 1.55
        cy = y + dy * s * 1.55
        circ = plt.Circle((cx, cy), 0.055, fill=False, ec=LINE, lw=1.2, zorder=6)
        ax.add_patch(circ)


def draw_entity(ax, key, box):
    x, y, w, h = box
    meta = ENTITIES[key]
    color = meta["color"]

    shadow = FancyBboxPatch(
        (x + 0.04, y - 0.04), w, h,
        boxstyle="round,pad=0.012,rounding_size=0.06",
        facecolor="#00000018", edgecolor="none", zorder=2,
    )
    ax.add_patch(shadow)

    body = FancyBboxPatch(
        (x, y), w, h,
        boxstyle="round,pad=0.012,rounding_size=0.06",
        facecolor=WHITE, edgecolor=color, linewidth=1.6, zorder=3,
    )
    ax.add_patch(body)

    header = Rectangle((x, y + h - HEADER_H), w, HEADER_H, facecolor=color, edgecolor="none", zorder=4)
    ax.add_patch(header)
    # clip header to rounded top by overlaying is hard; keep rectangular header

    ax.text(
        x + 0.10, y + h - 0.16, meta["title"],
        fontsize=8.2, fontweight="bold", color=WHITE, va="center", ha="left", zorder=5,
        fontfamily="DejaVu Sans",
    )
    ax.text(
        x + 0.10, y + h - 0.34, meta["subtitle"],
        fontsize=6.1, color="#E5E7EB", va="center", ha="left", zorder=5,
        fontfamily="DejaVu Sans",
    )

    for i, (name, dtype, kind) in enumerate(meta["attrs"]):
        ay = y + h - HEADER_H - (i + 1) * ROW_H
        if kind == "PK":
            ax.add_patch(Rectangle((x + 0.03, ay + 0.01), w - 0.06, ROW_H - 0.015, facecolor=PK_BG, edgecolor="none", zorder=4))
        elif kind == "FK":
            ax.add_patch(Rectangle((x + 0.03, ay + 0.01), w - 0.06, ROW_H - 0.015, facecolor=FK_BG, edgecolor="none", zorder=4))
        elif i % 2 == 1:
            ax.add_patch(Rectangle((x + 0.03, ay + 0.01), w - 0.06, ROW_H - 0.015, facecolor="#F8FAFC", edgecolor="none", zorder=4))

        badge = {"PK": "PK", "FK": "FK", "UK": "UK"}.get(kind, "")
        badge_color = {"PK": "#B45309", "FK": "#0369A1", "UK": "#7C3AED"}.get(kind, MUTED)
        label = f"{badge}  {name}" if badge else f"     {name}"
        ax.text(
            x + 0.10, ay + ROW_H * 0.48, label,
            fontsize=6.05, color=badge_color if badge else INK, va="center", ha="left", zorder=5,
            fontfamily="DejaVu Sans", fontweight="bold" if badge else "regular",
        )
        ax.text(
            x + w - 0.10, ay + ROW_H * 0.48, dtype,
            fontsize=5.5, color=MUTED, va="center", ha="right", zorder=5,
            fontfamily="DejaVu Sans",
        )


def draw_relationship(ax, boxes, rel, box_top, box_bottom):
    src, dst, s1, s2, f1, f2, c1, c2, dashed, label, highway = rel
    p1 = side_point(boxes[src], s1, f1)
    p2 = side_point(boxes[dst], s2, f2)
    pts = orthogonal_path(p1, s1, p2, s2, src, dst, highway, box_top, box_bottom)
    xs, ys = zip(*pts)
    ax.plot(
        xs, ys,
        color=DASH if dashed else LINE,
        lw=1.15 if dashed else 1.35,
        ls=(0, (4, 2.5)) if dashed else "-",
        zorder=1,
        solid_capstyle="round",
        solid_joinstyle="round",
    )

    def many(card):
        return card in ("N", "0..N", "1..N")

    def opt(card):
        return card.startswith("0")

    draw_crows_foot(ax, p1, s1, many=many(c1), optional=opt(c1))
    draw_crows_foot(ax, p2, s2, many=many(c2), optional=opt(c2))

    # label near midpoint of the routed path
    mid = pts[len(pts) // 2]
    if label:
        ax.text(
            mid[0], mid[1] + 0.07, label,
            fontsize=5.4, color=NAVY, ha="center", va="bottom", zorder=7,
            fontfamily="DejaVu Sans", fontstyle="italic",
            path_effects=[pe.withStroke(linewidth=2.5, foreground=BG)],
        )


def draw_legend(ax, fig_w, y0):
    ax.text(LEFT, y0 + 0.55, "LEGENDA", fontsize=8, fontweight="bold", color=NAVY, fontfamily="DejaVu Sans")

    items = [
        (PK_BG, "#B45309", "PK  Primary Key"),
        (FK_BG, "#0369A1", "FK  Foreign Key"),
        ("#F3E8FF", "#7C3AED", "UK  Unique Key"),
    ]
    x = LEFT
    for bg, fg, text in items:
        ax.add_patch(FancyBboxPatch((x, y0 + 0.12), 2.15, 0.32, boxstyle="round,pad=0.02,rounding_size=0.04", facecolor=bg, edgecolor=fg, lw=0.8, zorder=3))
        ax.text(x + 1.07, y0 + 0.28, text, fontsize=6.4, color=fg, ha="center", va="center", fontweight="bold", zorder=4, fontfamily="DejaVu Sans")
        x += 2.35

    # cardinality
    x += 0.2
    ax.text(x, y0 + 0.28, "Kardinalitas (Crow's Foot):", fontsize=6.4, color=INK, va="center", fontfamily="DejaVu Sans", fontweight="bold")
    x += 2.55
    samples = [
        ("1", False, False, "wajib satu"),
        ("0..1", False, True, "opsional satu"),
        ("N", True, False, "banyak"),
        ("0..N", True, True, "opsional banyak"),
    ]
    for card, many, optional, desc in samples:
        draw_crows_foot(ax, (x + 0.18, y0 + 0.28), "left", many=many, optional=optional)
        ax.text(x + 0.42, y0 + 0.28, f"{card}  {desc}", fontsize=6.2, color=INK, va="center", fontfamily="DejaVu Sans")
        x += 2.35

    ax.plot([LEFT + 12.2, LEFT + 13.0], [y0 - 0.12, y0 - 0.12], color=LINE, lw=1.4)
    ax.text(LEFT + 13.15, y0 - 0.12, "relasi utama (FK wajib / bisnis)", fontsize=6.2, color=INK, va="center", fontfamily="DejaVu Sans")
    ax.plot([LEFT + 16.6, LEFT + 17.4], [y0 - 0.12, y0 - 0.12], color=DASH, lw=1.2, ls=(0, (4, 2.5)))
    ax.text(LEFT + 17.55, y0 - 0.12, "relasi audit / opsional (reviewed_by, processed_by, generated_by, dsb.)", fontsize=6.2, color=INK, va="center", fontfamily="DejaVu Sans")

    groups = [
        ("#1B3A5F", "Organisasi & User"),
        ("#0F766E", "Simpanan"),
        ("#B45309", "Pinjaman"),
        ("#3730A3", "Transaksi"),
        ("#475569", "Notifikasi & Log"),
        ("#6D28D9", "Keamanan"),
        ("#334155", "Pengaturan"),
        ("#047857", "SHU"),
        ("#9F1239", "Rule Engine"),
    ]
    gx = LEFT
    gy = y0 - 0.48
    ax.text(LEFT, gy + 0.18, "Kelompok entitas:", fontsize=6.4, color=INK, fontweight="bold", fontfamily="DejaVu Sans")
    gx = LEFT + 2.15
    for color, name in groups:
        ax.add_patch(Rectangle((gx, gy + 0.05), 0.22, 0.18, facecolor=color, edgecolor="none"))
        ax.text(gx + 0.28, gy + 0.14, name, fontsize=6.1, color=INK, va="center", fontfamily="DejaVu Sans")
        gx += 2.35


def main():
    max_col_h = max(column_stack_height(c) for c in COLUMNS)
    box_bottom = 2.05
    box_top = box_bottom + max_col_h
    boxes = layout_boxes(box_top)

    max_x = max(x + w for x, y, w, h in boxes.values())
    fig_w = max_x + 0.45
    title_bottom = box_top + GROUP_H + 0.48
    fig_h = title_bottom + 1.02
    ylim_bottom = -0.08

    fig, ax = plt.subplots(figsize=(fig_w + 0.2, fig_h - ylim_bottom + 0.15), dpi=160)
    fig.patch.set_facecolor(BG)
    ax.set_facecolor(BG)
    ax.set_xlim(-0.12, fig_w)
    ax.set_ylim(ylim_bottom, fig_h)
    ax.axis("off")
    ax.set_aspect("equal")

    banner = FancyBboxPatch(
        (0.15, title_bottom), fig_w - 0.30, 0.92,
        boxstyle="round,pad=0.02,rounding_size=0.08",
        facecolor=NAVY, edgecolor="none", zorder=3,
    )
    ax.add_patch(banner)
    ax.add_patch(Rectangle((0.15, title_bottom), 0.12, 0.92, facecolor=GOLD, edgecolor="none", zorder=4))
    ax.text(
        fig_w / 2, title_bottom + 0.68,
        "ENTITY RELATIONSHIP DIAGRAM (ERD)",
        fontsize=13.5, fontweight="bold", color=WHITE, ha="center", va="center", zorder=5,
        fontfamily="DejaVu Sans",
    )
    ax.text(
        fig_w / 2, title_bottom + 0.42,
        "Aplikasi Simpan Pinjam Nasabah  •  Database: simpanpinjam_puninar",
        fontsize=8.5, color=GOLD, ha="center", va="center", zorder=5,
        fontfamily="DejaVu Sans",
    )
    ax.text(
        fig_w / 2, title_bottom + 0.20,
        "Koperasi Konsumen Karyawan PT. Puninar Jaya (KOPPUJA)",
        fontsize=7.6, color="#D6DEE8", ha="center", va="center", zorder=5,
        fontfamily="DejaVu Sans",
    )

    for i, label in enumerate(COLUMN_LABELS):
        cx = LEFT + i * (BOX_W + COL_GAP) + BOX_W / 2
        ax.add_patch(FancyBboxPatch(
            (LEFT + i * (BOX_W + COL_GAP), box_top + 0.06), BOX_W, 0.26,
            boxstyle="round,pad=0.01,rounding_size=0.04",
            facecolor=WHITE, edgecolor=NAVY, lw=0.8, zorder=3,
        ))
        ax.text(
            cx, box_top + 0.19, label,
            fontsize=6.4, fontweight="bold", color=NAVY, ha="center", va="center", zorder=4,
            fontfamily="DejaVu Sans",
        )

    for rel in RELS:
        draw_relationship(ax, boxes, rel, box_top + GROUP_H + 0.08, box_bottom)

    for key in ENTITIES:
        draw_entity(ax, key, boxes[key])

    draw_legend(ax, fig_w, 0.95)

    ax.text(
        fig_w / 2, 0.12,
        "Sumber skema: database/simpanpinjam_puninar.sql, add_shu_tables.sql, add_rule_engine_tables.sql, add_password_reset_table.sql  •  Notasi Crow's Foot  •  FK audit (reviewed_by, approved_by, processed_by, created_by) tercantum pada atribut entitas",
        fontsize=5.8, color=MUTED, ha="center", va="center", fontfamily="DejaVu Sans",
    )

    fig.savefig(PNG_PATH, dpi=160, bbox_inches="tight", facecolor=BG, pad_inches=0.18)
    plt.close(fig)
    print(f"Wrote {PNG_PATH} ({PNG_PATH.stat().st_size} bytes)")


if __name__ == "__main__":
    main()
