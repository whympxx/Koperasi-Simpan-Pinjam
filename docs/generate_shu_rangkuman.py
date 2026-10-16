#!/usr/bin/env python3
"""Generate PDF rangkuman penambahan perhitungan SHU."""

from __future__ import annotations

from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.platypus import ListFlowable, ListItem, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle

OUT_DIR = Path(__file__).resolve().parent
PDF_PATH = OUT_DIR / "Rangkuman_Penambahan_Perhitungan_SHU.pdf"

NAVY_HEX = colors.HexColor("#1B3A5F")
GOLD_HEX = colors.HexColor("#C9A227")
ROW_ALT = colors.HexColor("#F4F7FB")
HEADER_BG = colors.HexColor("#1B3A5F")

APP_NAME = "Aplikasi Simpan Pinjam Nasabah PT Puninar Jaya"
ORG_NAME = "Koperasi Konsumen Karyawan PT. Puninar Jaya (KOPPUJA)"
TODAY = "22 Agustus 2026"


def pdf_styles():
    styles = getSampleStyleSheet()
    styles.add(ParagraphStyle(name="CoverKicker", fontName="Times-Bold", fontSize=11, leading=14, alignment=TA_CENTER, textColor=NAVY_HEX, spaceAfter=6))
    styles.add(ParagraphStyle(name="CoverTitle", fontName="Times-Bold", fontSize=18, leading=22, alignment=TA_CENTER, textColor=NAVY_HEX, spaceAfter=4))
    styles.add(ParagraphStyle(name="CoverSub", fontName="Times-Bold", fontSize=12, leading=16, alignment=TA_CENTER, textColor=GOLD_HEX, spaceAfter=12))
    styles.add(ParagraphStyle(name="CoverApp", fontName="Times-Bold", fontSize=12, leading=16, alignment=TA_CENTER, spaceAfter=2))
    styles.add(ParagraphStyle(name="CoverOrg", fontName="Times-Italic", fontSize=11, leading=14, alignment=TA_CENTER, textColor=colors.HexColor("#333333"), spaceAfter=14))
    styles.add(ParagraphStyle(name="BodyJust", fontName="Times-Roman", fontSize=11, leading=15, alignment=TA_JUSTIFY, spaceAfter=8))
    styles.add(ParagraphStyle(name="H1", fontName="Times-Bold", fontSize=13, leading=17, textColor=NAVY_HEX, spaceBefore=12, spaceAfter=6))
    styles.add(ParagraphStyle(name="H2", fontName="Times-Bold", fontSize=11.5, leading=15, textColor=NAVY_HEX, spaceBefore=8, spaceAfter=4))
    styles.add(ParagraphStyle(name="CellH", fontName="Times-Bold", fontSize=8.5, leading=11, textColor=colors.white, alignment=TA_CENTER))
    styles.add(ParagraphStyle(name="Cell", fontName="Times-Roman", fontSize=8.5, leading=11, alignment=TA_LEFT, textColor=colors.HexColor("#222222")))
    styles.add(ParagraphStyle(name="CellC", fontName="Times-Roman", fontSize=8.5, leading=11, alignment=TA_CENTER, textColor=colors.HexColor("#222222")))
    styles.add(ParagraphStyle(name="Formula", fontName="Times-Bold", fontSize=11, leading=16, alignment=TA_CENTER, textColor=NAVY_HEX, spaceBefore=4, spaceAfter=8))
    styles.add(ParagraphStyle(name="BulletBody", fontName="Times-Roman", fontSize=11, leading=15, alignment=TA_JUSTIFY))
    styles.add(ParagraphStyle(name="Caption", fontName="Times-Italic", fontSize=9, leading=12, alignment=TA_CENTER, textColor=colors.HexColor("#555555"), spaceAfter=10))
    styles.add(ParagraphStyle(name="FooterCenter", fontName="Times-Italic", fontSize=10, leading=13, alignment=TA_CENTER, spaceBefore=8))
    return styles


def make_table(headers, rows, col_widths, center_cols=None):
    styles = pdf_styles()
    center_cols = center_cols or []
    data = [headers] + rows
    formatted = [[Paragraph(str(c), styles["CellH"]) for c in data[0]]]
    for row in data[1:]:
        cells = []
        for i, val in enumerate(row):
            st = styles["CellC"] if i in center_cols else styles["Cell"]
            cells.append(Paragraph(str(val), st))
        formatted.append(cells)
    t = Table(formatted, colWidths=col_widths, repeatRows=1)
    cmds = [
        ("BACKGROUND", (0, 0), (-1, 0), HEADER_BG),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("GRID", (0, 0), (-1, -1), 0.3, NAVY_HEX),
        ("LEFTPADDING", (0, 0), (-1, -1), 5),
        ("RIGHTPADDING", (0, 0), (-1, -1), 5),
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
    ]
    for i in range(1, len(data)):
        cmds.append(("BACKGROUND", (0, i), (-1, i), ROW_ALT if i % 2 else colors.white))
    t.setStyle(TableStyle(cmds))
    return t


def bullets(items):
    styles = pdf_styles()
    return ListFlowable(
        [ListItem(Paragraph(item, styles["BulletBody"]), leftIndent=12, bulletColor=NAVY_HEX) for item in items],
        bulletType="bullet",
        start="bullet",
        leftIndent=18,
        spaceAfter=8,
    )


def on_page(canvas, doc):
    canvas.saveState()
    canvas.setStrokeColor(NAVY_HEX)
    canvas.setLineWidth(1.2)
    canvas.line(1.8 * cm, A4[1] - 1.4 * cm, A4[0] - 1.8 * cm, A4[1] - 1.4 * cm)
    canvas.setFont("Times-Italic", 8)
    canvas.setFillColor(NAVY_HEX)
    canvas.drawString(1.8 * cm, A4[1] - 1.25 * cm, "Rangkuman Penambahan Perhitungan SHU")
    canvas.drawRightString(A4[0] - 1.8 * cm, A4[1] - 1.25 * cm, "KOPPUJA")
    canvas.line(1.8 * cm, 1.4 * cm, A4[0] - 1.8 * cm, 1.4 * cm)
    canvas.drawCentredString(A4[0] / 2, 0.85 * cm, f"{APP_NAME}  |  Halaman {doc.page}")
    canvas.restoreState()


def build_pdf():
    styles = pdf_styles()
    story = []
    usable = A4[0] - 3.6 * cm

    story.append(Paragraph("DOKUMEN RANGKUMAN PENGEMBANGAN SISTEM", styles["CoverKicker"]))
    story.append(Paragraph("PENAMBAHAN PERHITUNGAN DAN LAPORAN SHU", styles["CoverTitle"]))
    story.append(Paragraph("Sisa Hasil Usaha — Tahun Buku Koperasi", styles["CoverSub"]))
    story.append(Paragraph(APP_NAME, styles["CoverApp"]))
    story.append(Paragraph(ORG_NAME, styles["CoverOrg"]))

    story.append(make_table(
        ["Atribut", "Keterangan"],
        [
            ["Jenis dokumen", "Rangkuman penambahan fitur"],
            ["Modul yang ditambah", "Perhitungan, pembagian, dan laporan SHU"],
            ["Pengguna", "Admin/pengurus dan nasabah (anggota)"],
            ["Dasar kebijakan", "Praktik koperasi simpan pinjam KOPPUJA"],
            ["Tanggal dokumen", TODAY],
            ["Versi dokumen", "1.0"],
        ],
        [5.0 * cm, usable - 5.0 * cm],
    ))
    story.append(Spacer(1, 6))

    story.append(Paragraph("1. Latar belakang aplikasi", styles["H1"]))
    story.append(Paragraph(
        "Aplikasi Simpan Pinjam Nasabah PT Puninar Jaya adalah sistem web untuk mengelola keanggotaan, "
        "simpanan wajib, pengajuan pinjaman, angsuran, dan laporan keuangan Koperasi Konsumen Karyawan "
        "PT. Puninar Jaya (KOPPUJA). Kebijakan pinjaman mengikuti RAT Tahun Buku 2025: plafon dihitung "
        "dari simpanan wajib (2× dengan potongan HRD, 1× untuk outsourcing/angsur mandiri), tenor standar "
        "maksimal 12 bulan (kebijakan khusus 18 bulan), dan simpanan sukarela telah ditiadakan.",
        styles["BodyJust"],
    ))
    story.append(Paragraph(
        "Sebelum penambahan ini, sistem sudah mencatat setoran simpanan, pencairan pinjaman, dan pembayaran "
        "angsuran (pokok, bunga, serta denda). Yang belum tersedia adalah perhitungan Sisa Hasil Usaha (SHU) "
        "serta laporan pembagiannya kepada anggota. Fitur SHU ditambahkan agar pengurus dapat menghitung "
        "hasil usaha koperasi per tahun buku dan anggota dapat melihat hak SHU-nya secara transparan.",
        styles["BodyJust"],
    ))

    story.append(Paragraph("2. Tujuan penambahan", styles["H1"]))
    story.append(bullets([
        "Menghitung SHU secara otomatis dari data transaksi yang sudah ada (bunga pinjaman terbayar dan denda).",
        "Membagi SHU sesuai alokasi koperasi: cadangan, jasa anggota, dana pengurus, dana karyawan, dana pendidikan, dan dana sosial.",
        "Membagikan porsi jasa anggota ke tiap anggota berdasarkan partisipasi simpanan wajib dan jasa pinjaman.",
        "Menyediakan laporan yang dapat dicetak, diunduh CSV, disimpan sebagai draf, atau dikunci sebagai final.",
        "Menampilkan estimasi SHU di dashboard nasabah agar hak anggota dapat dipantau sepanjang tahun buku.",
    ]))

    story.append(Paragraph("3. Rumus perhitungan SHU", styles["H1"]))
    story.append(Paragraph(
        "Perhitungan memakai dasar kas: hanya bunga dan denda dari angsuran yang sudah berstatus dibayar "
        "pada tahun buku yang dihitung. Biaya operasional dan pendapatan lain diisi pengurus pada saat penyusunan laporan.",
        styles["BodyJust"],
    ))
    story.append(Paragraph("Pendapatan = Bunga pinjaman terbayar + Denda keterlambatan + Pendapatan lain", styles["Formula"]))
    story.append(Paragraph("SHU = Pendapatan − Biaya operasional", styles["Formula"]))
    story.append(Paragraph(
        "Jika hasil SHU negatif (beban lebih besar dari pendapatan), sistem menampilkan peringatan dan "
        "tidak membagikan SHU kepada anggota sampai pendapatan melebihi beban.",
        styles["BodyJust"],
    ))

    story.append(Paragraph("4. Pembagian SHU koperasi", styles["H1"]))
    story.append(Paragraph(
        "Setelah SHU dihitung, nilai positif dibagi menurut persentase alokasi. Persentase ini dapat diubah "
        "pada menu Pengaturan, dengan syarat total alokasi utama berjumlah 100%. Nilai bawaan sesuai praktik umum koperasi:",
        styles["BodyJust"],
    ))
    story.append(make_table(
        ["Komponen", "Persentase", "Keterangan"],
        [
            ["Cadangan koperasi", "25%", "Ditahan untuk permodalan dan cadangan koperasi"],
            ["Jasa anggota", "40%", "Dibagikan kepada anggota (jasa simpanan dan jasa pinjaman)"],
            ["Dana pengurus", "10%", "Alokasi untuk pengurus"],
            ["Dana karyawan", "5%", "Alokasi untuk karyawan koperasi"],
            ["Dana pendidikan", "10%", "Alokasi pendidikan perkoperasian"],
            ["Dana sosial", "10%", "Alokasi kegiatan sosial"],
            ["Total", "100%", "Wajib berjumlah 100%"],
        ],
        [4.2 * cm, 2.6 * cm, usable - 6.8 * cm],
        center_cols=[1],
    ))
    story.append(Paragraph("Tabel 1. Alokasi pembagian SHU (nilai bawaan)", styles["Caption"]))

    story.append(Paragraph("5. Pembagian SHU kepada anggota", styles["H1"]))
    story.append(Paragraph(
        "Hanya porsi jasa anggota yang dibagikan ke rekening hak anggota. Porsi tersebut dipecah menjadi "
        "jasa simpanan 50% dan jasa pinjaman 50% (dapat diubah, jumlah keduanya harus 100%).",
        styles["BodyJust"],
    ))
    story.append(Paragraph(
        "SHU jasa simpanan anggota = (saldo simpanan wajib anggota / total simpanan wajib) × porsi jasa simpanan",
        styles["Formula"],
    ))
    story.append(Paragraph(
        "SHU jasa pinjaman anggota = (bunga pinjaman yang dibayar anggota / total bunga terbayar) × porsi jasa pinjaman",
        styles["Formula"],
    ))
    story.append(Paragraph("SHU anggota = SHU jasa simpanan + SHU jasa pinjaman", styles["Formula"]))
    story.append(Paragraph(
        "Anggota yang dihitung adalah nasabah berstatus aktif atau resign yang memiliki saldo simpanan wajib "
        "dan/atau telah membayar bunga pinjaman pada tahun buku tersebut. Pembulatan ke satuan rupiah, "
        "selisih pembulatan dialihkan ke anggota terakhir agar total tetap sama dengan porsi jasa anggota.",
        styles["BodyJust"],
    ))

    story.append(Paragraph("6. Fitur yang ditambahkan", styles["H1"]))
    story.append(Paragraph("6.1 Untuk admin / pengurus", styles["H2"]))
    story.append(bullets([
        "Menu SHU pada panel admin (admin-shu.php) untuk menghitung SHU per tahun buku.",
        "Input biaya operasional dan pendapatan lain, lalu tombol hitung ulang.",
        "Ringkasan pendapatan, beban, nilai SHU, grafik pembagian, dan tabel rincian per anggota.",
        "Cetak laporan dan unduh CSV rincian anggota.",
        "Simpan sebagai draf atau kunci sebagai laporan final; laporan final dapat dibuka kunci bila perlu dihitung ulang.",
        "Ringkasan SHU juga tampil di halaman Laporan Keuangan.",
        "Pengaturan alokasi persentase SHU pada menu Pengaturan Sistem.",
    ]))
    story.append(Paragraph("6.2 Untuk nasabah / anggota", styles["H2"]))
    story.append(bullets([
        "Menu SHU pada navigasi anggota (shu.php) untuk melihat hak SHU tahun buku yang dipilih.",
        "Kartu estimasi SHU pada dashboard, memuat jasa simpanan, jasa pinjaman, dan total SHU koperasi.",
        "Penjelasan rumus singkat dan status estimasi atau sudah dikunci pengurus.",
        "Fasilitas cetak laporan SHU pribadi.",
    ]))

    story.append(Paragraph("7. Cara menggunakan", styles["H1"]))
    story.append(make_table(
        ["Langkah", "Pengguna", "Kegiatan"],
        [
            ["1", "Admin", "Buka menu SHU, pilih tahun buku."],
            ["2", "Admin", "Isi biaya operasional dan pendapatan lain (jika ada), lalu hitung SHU."],
            ["3", "Admin", "Periksa ringkasan, grafik alokasi, dan rincian per anggota."],
            ["4", "Admin", "Cetak atau unduh CSV bila diperlukan untuk RAT."],
            ["5", "Admin", "Simpan draf, atau kunci sebagai final setelah disahkan."],
            ["6", "Nasabah", "Buka Dashboard atau menu SHU untuk melihat hak SHU tahun berjalan."],
        ],
        [2.0 * cm, 2.8 * cm, usable - 4.8 * cm],
        center_cols=[0, 1],
    ))
    story.append(Paragraph("Tabel 2. Alur penggunaan fitur SHU", styles["Caption"]))

    story.append(Paragraph("8. File dan komponen yang ditambah", styles["H1"]))
    story.append(make_table(
        ["File / komponen", "Fungsi"],
        [
            ["classes/SHUManager.php", "Mesin hitung SHU, pembagian alokasi, pembagian per anggota, simpan/kunci laporan, dan pembuatan tabel otomatis."],
            ["admin-shu.php", "Halaman admin: perhitungan, laporan, cetak, CSV, simpan draf, dan kunci final."],
            ["shu.php", "Halaman nasabah: laporan hak SHU pribadi per tahun buku."],
            ["admin-reports.php", "Menampilkan ringkasan SHU tahun berjalan pada Laporan Keuangan."],
            ["admin-settings.php", "Form pengaturan persentase alokasi SHU."],
            ["dashboard.php", "Kartu estimasi SHU dan tautan ke laporan anggota."],
            ["includes/admin-layout.php", "Item menu SHU pada sidebar admin."],
            ["database/add_shu_tables.sql", "Skema tabel shu_periods, shu_member_shares, dan pengaturan alokasi."],
        ],
        [5.2 * cm, usable - 5.2 * cm],
    ))
    story.append(Paragraph("Tabel 3. Komponen teknis penambahan SHU", styles["Caption"]))

    story.append(Paragraph("9. Data yang dipakai sistem", styles["H1"]))
    story.append(make_table(
        ["Sumber data", "Dipakai untuk"],
        [
            ["loan_schedules (status paid/partial, paid_date)", "Bunga pinjaman terbayar dan denda sebagai pendapatan usaha."],
            ["savings_accounts.balance", "Dasar jasa simpanan (saldo simpanan wajib anggota)."],
            ["users (role nasabah)", "Daftar anggota yang berhak dihitung SHU-nya."],
            ["Input biaya operasional &amp; pendapatan lain", "Beban dan pendapatan di luar bunga/denda."],
            ["system_settings (shu_pct_*)", "Persentase alokasi cadangan, jasa anggota, dan dana-dana."],
            ["shu_periods &amp; shu_member_shares", "Penyimpanan laporan draf/final beserta rincian anggota."],
        ],
        [6.4 * cm, usable - 6.4 * cm],
    ))

    story.append(Paragraph("10. Ringkasan", styles["H1"]))
    story.append(Paragraph(
        "Penambahan modul SHU melengkapi aplikasi simpan pinjam KOPPUJA dari pencatatan transaksi menjadi "
        "perhitungan hasil usaha koperasi. Sistem menghitung SHU dari bunga dan denda yang benar-benar "
        "sudah dibayar, membaginya menurut alokasi yang dapat diatur pengurus, dan meneruskan porsi jasa "
        "anggota kepada setiap anggota berdasarkan simpanan wajib dan partisipasi pinjaman. Admin memperoleh "
        "laporan lengkap untuk RAT, sedangkan anggota memperoleh transparansi hak SHU di dashboard dan menu SHU.",
        styles["BodyJust"],
    ))
    story.append(Paragraph(
        "Fitur ini tidak mencairkan SHU ke rekening secara otomatis; yang disediakan adalah perhitungan dan laporan. "
        "Pencairan atau pemindahbukuan tetap menjadi keputusan pengurus setelah laporan dikunci.",
        styles["BodyJust"],
    ))

    story.append(Spacer(1, 10))
    story.append(Paragraph(f"{ORG_NAME}", styles["FooterCenter"]))
    story.append(Paragraph(TODAY, styles["FooterCenter"]))

    doc = SimpleDocTemplate(
        str(PDF_PATH),
        pagesize=A4,
        leftMargin=1.8 * cm,
        rightMargin=1.8 * cm,
        topMargin=2.0 * cm,
        bottomMargin=2.0 * cm,
        title="Rangkuman Penambahan Perhitungan SHU",
        author=ORG_NAME,
        subject="Rangkuman fitur SHU pada Aplikasi Simpan Pinjam Nasabah PT Puninar Jaya",
    )
    doc.build(story, onFirstPage=on_page, onLaterPages=on_page)
    print(f"Wrote {PDF_PATH}")


if __name__ == "__main__":
    build_pdf()
