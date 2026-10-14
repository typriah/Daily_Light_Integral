#!/usr/bin/env python3
# run_dli.py - Server socket menerima data 8 kolom, hitung DLI harian akumulatif per hari
#
# Perubahan dari versi sebelumnya:
#   - Parse nilai RGBW dari field source ESP32 (format: "ESP32_R70G20B50W30")
#   - Simpan red_pct, green_pct, blue_pct, white_pct ke light_monitoring
#   - Auto-tambah kolom RGBW ke tabel jika belum ada (migrasi aman, tidak hapus data lama)
#   - Log console diperkaya dengan info RGBW

import socket
import MySQLdb
import threading
import re
from datetime import datetime

DB_HOST = "localhost"
DB_USER = "root"
DB_PASS = ""
DB_NAME = "dlidb"

HOST = "0.0.0.0"
PORT = 12345


# ══════════════════════════════════════════════════════════════════
#  DATABASE HELPERS
# ══════════════════════════════════════════════════════════════════

def get_db_connection():
    return MySQLdb.connect(DB_HOST, DB_USER, DB_PASS, DB_NAME)


def ensure_rgbw_columns():
    """
    Tambahkan kolom RGBW ke tabel light_monitoring jika belum ada.
    Aman dijalankan berulang kali — tidak menghapus data yang sudah ada.
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    columns_to_add = [
        ("red_pct",   "TINYINT UNSIGNED DEFAULT NULL COMMENT 'Proporsi Red LED (0-100)'"),
        ("green_pct", "TINYINT UNSIGNED DEFAULT NULL COMMENT 'Proporsi Green LED (0-100)'"),
        ("blue_pct",  "TINYINT UNSIGNED DEFAULT NULL COMMENT 'Proporsi Blue LED (0-100)'"),
        ("white_pct", "TINYINT UNSIGNED DEFAULT NULL COMMENT 'Proporsi White LED (0-100)'"),
    ]

    # Cek kolom yang sudah ada
    cursor.execute("SHOW COLUMNS FROM light_monitoring")
    existing = {row[0] for row in cursor.fetchall()}

    for col_name, col_def in columns_to_add:
        if col_name not in existing:
            sql = f"ALTER TABLE light_monitoring ADD COLUMN {col_name} {col_def}"
            cursor.execute(sql)
            print(f"[DB] Kolom '{col_name}' ditambahkan ke light_monitoring")
        else:
            print(f"[DB] Kolom '{col_name}' sudah ada, dilewati")

    conn.commit()
    cursor.close()
    conn.close()


def get_last_dli_today(date_str):
    """Mengembalikan nilai dli_daily terakhir pada tanggal tertentu (atau 0 jika belum ada)"""
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute(
        "SELECT dli_daily FROM light_monitoring WHERE DATE(timestamp)=%s ORDER BY timestamp DESC LIMIT 1",
        (date_str,)
    )
    row = cursor.fetchone()
    cursor.close()
    conn.close()
    return float(row[0]) if row else 0.0


# ══════════════════════════════════════════════════════════════════
#  RGBW PARSER
#  Membaca nilai R/G/B/W dari field source ESP32.
#  Format yang didukung:
#    "ESP32_R70G20B50W30"   → r=70, g=20, b=50, w=30
#    "ESP32"                → semua None (data lama / tanpa info RGBW)
#    string lain            → semua None
# ══════════════════════════════════════════════════════════════════

_RGBW_PATTERN = re.compile(
    r'R(?P<r>\d{1,3})G(?P<g>\d{1,3})B(?P<b>\d{1,3})W(?P<w>\d{1,3})',
    re.IGNORECASE
)

def parse_rgbw_from_source(source: str):
    """
    Kembalikan dict {'r': int, 'g': int, 'b': int, 'w': int}
    atau None jika format source tidak mengandung info RGBW.
    """
    match = _RGBW_PATTERN.search(source)
    if not match:
        return None
    return {
        'r': min(100, int(match.group('r'))),
        'g': min(100, int(match.group('g'))),
        'b': min(100, int(match.group('b'))),
        'w': min(100, int(match.group('w'))),
    }


# ══════════════════════════════════════════════════════════════════
#  SIMPAN DATA
# ══════════════════════════════════════════════════════════════════

def simpan_data(parts):
    # parts: [lux, ppfd, dli_cumulative, led_brightness, interval, temperature, humidity, source]
    if len(parts) < 8:
        print(f"Data tidak lengkap: {parts}")
        return
    try:
        lux         = float(parts[0])
        ppfd        = float(parts[1])
        # dli_cumulative dari ESP32 tidak dipakai, kita hitung ulang di server
        brightness  = int(parts[3])
        interval    = int(parts[4])
        temperature = float(parts[5]) if parts[5] else None
        humidity    = float(parts[6]) if parts[6] else None
        source      = parts[7].strip()
    except Exception as e:
        print(f"Error parsing data: {e} | {parts}")
        return

    # ── Parse RGBW dari source ──────────────────────────────────
    rgbw = parse_rgbw_from_source(source)
    red_pct   = rgbw['r'] if rgbw else None
    green_pct = rgbw['g'] if rgbw else None
    blue_pct  = rgbw['b'] if rgbw else None
    white_pct = rgbw['w'] if rgbw else None

    # ── Hitung DLI harian akumulatif ────────────────────────────
    dli_contrib  = ppfd * interval / 1_000_000.0
    today        = datetime.now().date()
    last_dli     = get_last_dli_today(today)
    new_dli_daily = last_dli + dli_contrib

    # ── Simpan ke database ──────────────────────────────────────
    conn   = get_db_connection()
    cursor = conn.cursor()

    sql = """
        INSERT INTO light_monitoring
            (timestamp, lux, ppfd, dli_cumulative, dli_daily, led_brightness,
             sampling_interval_sec, temperature, humidity, sensor_status,
             red_pct, green_pct, blue_pct, white_pct)
        VALUES
            (NOW(), %s, %s, %s, %s, %s, %s, %s, %s, %s,
             %s, %s, %s, %s)
    """
    cursor.execute(sql, (
        lux, ppfd, new_dli_daily, new_dli_daily, brightness,
        interval, temperature, humidity, source,
        red_pct, green_pct, blue_pct, white_pct
    ))
    conn.commit()
    cursor.close()
    conn.close()

    # ── Log ─────────────────────────────────────────────────────
    rgbw_str = (
        f"R={red_pct}% G={green_pct}% B={blue_pct}% W={white_pct}%"
        if rgbw else "RGBW=n/a"
    )
    print(
        f"[{datetime.now().strftime('%Y-%m-%d %H:%M:%S')}] "
        f"{source}: Lux={lux:.1f}, PPFD={ppfd:.2f}, "
        f"DLI_harian={new_dli_daily:.4f} mol/m² | {rgbw_str}"
    )


# ══════════════════════════════════════════════════════════════════
#  SOCKET HANDLER (tidak berubah dari versi asli)
# ══════════════════════════════════════════════════════════════════

def handle_client(conn, addr):
    print(f"Terhubung dari {addr}")
    buffer = ""
    while True:
        try:
            data = conn.recv(1024).decode('utf-8')
            if not data:
                break
            buffer += data
            if "\n" in buffer:
                lines = buffer.split("\n")
                for line in lines[:-1]:
                    line = line.strip()
                    if line:
                        parts = line.split(",")
                        if len(parts) == 8:
                            simpan_data(parts)
                        else:
                            print(f"Format salah (jumlah kolom {len(parts)}): {line}")
                buffer = lines[-1]
        except Exception as e:
            print(f"Koneksi error: {e}")
            break
    conn.close()


def main():
    # Pastikan kolom RGBW sudah ada sebelum mulai menerima data
    print("[DB] Memeriksa & mempersiapkan kolom RGBW...")
    ensure_rgbw_columns()

    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    server.bind((HOST, PORT))
    server.listen(5)
    print(f"Server berjalan di {HOST}:{PORT}")

    while True:
        conn, addr = server.accept()
        threading.Thread(target=handle_client, args=(conn, addr), daemon=True).start()


if __name__ == "__main__":
    main()