#!/usr/bin/env python3
# sim_run_dli.py - Simulator mengirim data 8 kolom (source = "SIM_R..G..B..W..")
#
# Perubahan dari versi sebelumnya:
#   - Source field sekarang menyertakan info RGBW aktif
#     Format: "SIM_R70G20B50W30" (sama dengan format ESP32 di dli_rgbw.ino)
#   - RGBW bisa diubah via input keyboard tanpa restart (tekan Enter lalu ketik preset)
#   - Preset tanaman yang sama dengan dashboard PHP tersedia
#   - Simulator tetap berjalan normal tanpa input (default: preset Sayuran Daun)

import socket
import time
import random
import threading
from datetime import datetime

HOST     = "127.0.0.1"
PORT     = 12345
INTERVAL = 2  # detik, sama dengan ESP32

# ── Preset tanaman (sama dengan light_monitoring.php) ──────────────
PLANT_PRESETS = {
    "1": {"name": "Sayuran Daun",  "r": 65, "g": 15, "b": 55, "w": 25},
    "2": {"name": "Sayuran Buah",  "r": 75, "g": 10, "b": 45, "w": 35},
    "3": {"name": "Tanaman Bunga", "r": 60, "g": 20, "b": 65, "w": 20},
    "4": {"name": "Penyemaian",    "r": 50, "g": 25, "b": 70, "w": 30},
    "5": {"name": "Herbal/Rempah", "r": 55, "g": 30, "b": 50, "w": 40},
}

# State RGBW aktif (bisa diubah via input thread)
rgbw_lock = threading.Lock()
rgbw_state = {"r": 65, "g": 15, "b": 55, "w": 25}  # default: Sayuran Daun


def print_menu():
    print("\n" + "─" * 50)
    print("  Preset Tanaman (ketik angka lalu Enter):")
    for k, v in PLANT_PRESETS.items():
        p = v
        print(f"  [{k}] {p['name']:<18} R={p['r']}% G={p['g']}% B={p['b']}% W={p['w']}%")
    print("  [c] Custom  – masukkan R G B W manual")
    print("  [s] Status  – tampilkan RGBW aktif")
    print("─" * 50)


def input_thread():
    """Thread terpisah untuk terima input keyboard tanpa mengganggu loop kirim data."""
    print_menu()
    while True:
        try:
            choice = input().strip().lower()
        except EOFError:
            break

        if choice in PLANT_PRESETS:
            p = PLANT_PRESETS[choice]
            with rgbw_lock:
                rgbw_state.update({"r": p["r"], "g": p["g"], "b": p["b"], "w": p["w"]})
            print(f"  ✓ Preset '{p['name']}' aktif: R={p['r']}% G={p['g']}% B={p['b']}% W={p['w']}%")

        elif choice == "c":
            try:
                print("  Masukkan nilai R G B W (0-100) dipisah spasi: ", end="", flush=True)
                vals = input().strip().split()
                r, g, b, w = [max(0, min(100, int(v))) for v in vals[:4]]
                with rgbw_lock:
                    rgbw_state.update({"r": r, "g": g, "b": b, "w": w})
                print(f"  ✓ Custom RGBW aktif: R={r}% G={g}% B={b}% W={w}%")
            except Exception:
                print("  ✗ Format salah. Contoh: 70 20 50 30")

        elif choice == "s":
            with rgbw_lock:
                s = rgbw_state.copy()
            print(f"  RGBW aktif → R={s['r']}% G={s['g']}% B={s['b']}% W={s['w']}%")

        else:
            print_menu()


def kirim_data():
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    sock.connect((HOST, PORT))
    print(f"Simulator terhubung ke server {HOST}:{PORT}")

    lux = 2000.0

    while True:
        lux += random.uniform(-200, 200)
        lux = max(100, min(10000, lux))
        ppfd = lux * 0.0185

        brightness = int((1 - (lux / 10000)) * 255)
        brightness = max(0, min(255, brightness))

        temperature = random.uniform(20, 35)
        humidity    = random.uniform(50, 90)

        # Ambil RGBW aktif secara thread-safe
        with rgbw_lock:
            r = rgbw_state["r"]
            g = rgbw_state["g"]
            b = rgbw_state["b"]
            w = rgbw_state["w"]

        # Format source sama persis dengan ESP32 agar run_dli.py bisa parse
        source = f"SIM_R{r}G{g}B{b}W{w}"

        line = (f"{lux:.2f},{ppfd:.2f},0.0000,"
                f"{brightness},{INTERVAL},"
                f"{temperature:.1f},{humidity:.1f},{source}\n")

        sock.sendall(line.encode())
        print(f"[{datetime.now().strftime('%H:%M:%S')}] "
              f"Lux={lux:.0f} PPFD={ppfd:.2f} Bright={brightness} "
              f"R={r}% G={g}% B={b}% W={w}%")

        time.sleep(INTERVAL)


if __name__ == "__main__":
    # Jalankan input keyboard di thread terpisah (daemon agar ikut mati saat main selesai)
    t = threading.Thread(target=input_thread, daemon=True)
    t.start()

    kirim_data()