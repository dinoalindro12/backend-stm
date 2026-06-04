# API STM — PT Surya Tamado Mandiri

REST API backend untuk sistem manajemen SDM PT Surya Tamado Mandiri, mencakup pengelolaan karyawan, penggajian, tagihan perusahaan, rekruitmen, lowongan kerja, dan kontak.

---

## Tech Stack

| Komponen | Detail |
|---|---|
| Framework | Laravel 12 |
| PHP | ^8.2 |
| Autentikasi | Laravel Sanctum 4 |
| Database | MySQL / SQLite |
| Export Excel | Maatwebsite Excel 3.1 |
| Export PDF | Barryvdh DomPDF 3.1 |
| Testing | PestPHP 4 |

---

## Instalasi

```bash
# 1. Clone repository
git clone <repo-url>
cd apiku

# 2. Install dependency
composer install

# 3. Salin file environment
cp .env.example .env

# 4. Generate application key
php artisan key:generate

# 5. Konfigurasi database di .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database
DB_USERNAME=root
DB_PASSWORD=

# 6. Jalankan migrasi
php artisan migrate

# 7. Jalankan server
php artisan serve
```

> Atau gunakan shortcut: `composer run setup`

---

## Autentikasi

API ini menggunakan **Laravel Sanctum** dengan Bearer Token.

Sertakan header berikut pada setiap request yang memerlukan autentikasi:

```
Authorization: Bearer {token}
Accept: application/json
```

Token diperoleh setelah login atau register.

> Maksimal **5 akun admin** yang bisa terdaftar.

---

## Format Response

Semua response menggunakan format JSON yang konsisten:

**Sukses (single data):**
```json
{
  "data": { ... },
  "success": true,
  "message": "Pesan sukses"
}
```

**Sukses (koleksi data):**
```json
{
  "data": [ ... ],
  "links": { ... },
  "meta": { ... },
  "success": true,
  "message": "Pesan sukses"
}
```

**Gagal:**
```json
{
  "success": false,
  "message": "Pesan error",
  "errors": { ... }
}
```

---

## Endpoints

### 🔐 Autentikasi

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| POST | `/api/register` | ❌ | Registrasi admin baru |
| POST | `/api/login` | ❌ | Login admin |
| POST | `/api/logout` | ✅ | Logout (hapus token) |
| POST | `/api/ganti-password` | ✅ | Ganti password |
| DELETE | `/api/hapus-akun` | ✅ | Hapus akun sendiri |

**Body Register / Login:**
```json
{
  "name": "Admin",
  "email": "admin@example.com",
  "password": "P@ssword1"
}
```

---

### 📊 Dashboard

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/api/dashboard/dashboard` | ✅ | Semua data dashboard |

**Query params:** `?months=6` (jumlah bulan untuk chart, default 6)

---

### 👥 Karyawan

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/api/karyawan` | ✅ | Daftar karyawan |
| POST | `/api/karyawan` | ✅ | Tambah karyawan |
| GET | `/api/karyawan/{id}` | ✅ | Detail karyawan |
| PUT | `/api/karyawan/{id}` | ✅ | Update karyawan |
| DELETE | `/api/karyawan/{id}` | ✅ | Hapus karyawan (soft delete) |
| GET | `/api/karyawan/{id}/download-kartu` | ✅ | Download kartu karyawan (PDF) |
| GET | `/api/karyawan/{id}/preview-kartu` | ✅ | Preview kartu karyawan (PDF) |
| POST | `/api/karyawan/bulk-download-kartu` | ✅ | Download kartu karyawan massal (PDF) |
| GET | `/api/karyawan/download-excel` | ✅ | Download data karyawan (Excel) |
| POST | `/api/karyawan/import` | ✅ | Import karyawan dari Excel |
| GET | `/api/karyawan/import/template` | ✅ | Download template import |

**Query params index:** `?status_aktif=1&posisi=keamanan&search=nama&sort_by=nama_lengkap&sort_order=asc&per_page=10`

**Nilai posisi:** `jasa` `supir` `keamanan` `cleaning_service` `operator`

---

### 💰 Penggajian

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/api/penggajian` | ✅ | Daftar penggajian |
| POST | `/api/penggajian` | ✅ | Tambah penggajian |
| GET | `/api/penggajian/{id}` | ✅ | Detail penggajian |
| PUT | `/api/penggajian/{id}` | ✅ | Update penggajian |
| DELETE | `/api/penggajian/{id}` | ✅ | Hapus penggajian |
| POST | `/api/penggajian/{id}/cetak` | ✅ | Cetak slip gaji |
| GET | `/api/penggajian/{id}/send-whatsapp` | ✅ | Kirim slip ke WhatsApp |
| POST | `/api/penggajian/send-whatsapp-bulk` | ✅ | Kirim slip massal ke WhatsApp |
| POST | `/api/penggajian/batch` | ✅ | Tambah penggajian massal |
| POST | `/api/penggajian/copy-previous-month` | ✅ | Salin data dari bulan lalu |
| POST | `/api/penggajian/preview-copy` | ✅ | Preview sebelum salin |
| GET | `/api/penggajian/summary/statistik` | ✅ | Statistik penggajian |
| GET | `/api/penggajian/available-months-list` | ✅ | Daftar bulan yang tersedia |
| GET | `/api/penggajian/excel` | ✅ | Export Excel penggajian |
| GET | `/api/penggajian/preview` | ✅ | Preview data sebelum export |

**Query params index:** `?bulan=12&tahun=2025&posisi=keamanan&status=1&cetak_status=sudah`

**Kalkulasi otomatis saat store/update:**
| Field | Rumus |
|---|---|
| `bpjs_kesehatan` | 1% × penghasilan kotor (0 jika hari kerja < 7) |
| `bpjs_jht` | 2% × penghasilan kotor (0 jika hari kerja < 7) |
| `bpjs_jp` | 1% × penghasilan kotor (0 jika hari kerja < 7) |
| `upah_kotor_karyawan` | (gaji_harian × hari_kerja) + lembur + thr |
| `upah_diterima` | upah_kotor − total_bpjs |

---

### 🏢 Tagihan Perusahaan

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/api/tagihan` | ✅ | Daftar tagihan |
| POST | `/api/tagihan` | ✅ | Tambah tagihan |
| GET | `/api/tagihan/{id}` | ✅ | Detail tagihan |
| PUT | `/api/tagihan/{id}` | ✅ | Update tagihan |
| DELETE | `/api/tagihan/{id}` | ✅ | Hapus tagihan (soft delete) |
| POST | `/api/tagihan/{id}/restore` | ✅ | Pulihkan tagihan |
| POST | `/api/tagihan/import` | ✅ | Import tagihan massal |
| POST | `/api/tagihan/bulk-delete` | ✅ | Hapus tagihan massal |
| POST | `/api/tagihan/copy-previous-month` | ✅ | Salin data dari bulan lalu |
| GET | `/api/tagihan/summary` | ✅ | Statistik tagihan |
| GET | `/api/tagihan/available-months` | ✅ | Daftar bulan yang tersedia |
| GET | `/api/tagihan/export/excel` | ✅ | Export Excel tagihan |
| GET | `/api/tagihan/export/preview` | ✅ | Preview data sebelum export |

**Query params index:** `?bulan=12&tahun=2025&posisi=operator`

**Kalkulasi otomatis saat store/update:**
| Field | Rumus |
|---|---|
| `bpjs_kesehatan` | 4% × penghasilan kotor (0 jika hari kerja < 7) |
| `jht` | 3.7% × penghasilan kotor (0 jika hari kerja < 7) |
| `jp` | 2% × penghasilan kotor (0 jika hari kerja < 7) |
| `jkk` | 0.24% × penghasilan kotor (0 jika hari kerja < 7) |
| `jkm` | 0.3% × penghasilan kotor (0 jika hari kerja < 7) |
| `upah_diterima_pekerja` | (gaji_harian × hari_kerja) + lembur + thr |
| `upah_total` | upah_diterima_pekerja + semua iuran & fee perusahaan |

---

### 📋 Lowongan Kerja

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| GET | `/api/lowongan-kerja` | ❌ | Daftar lowongan aktif (publik) |
| GET | `/api/lowongan-kerja/statistik` | ❌ | Statistik lowongan (publik) |
| GET | `/api/lowongan-kerja/{id}` | ❌ | Detail lowongan aktif (publik) |
| GET | `/api/lowongan` | ✅ | Daftar semua lowongan (admin) |
| POST | `/api/lowongan` | ✅ | Buat lowongan baru |
| GET | `/api/lowongan/{id}` | ✅ | Detail lowongan (admin) |
| PUT | `/api/lowongan/{id}` | ✅ | Update lowongan |
| DELETE | `/api/lowongan/{id}` | ✅ | Hapus lowongan |
| GET | `/api/lowongan/{id}/pelamar` | ✅ | Daftar pelamar per lowongan |

**Query params publik:** `?posisi=keamanan&jenis_kerja=Full Time&lokasi=jakarta&search=kata`

**Nilai status_lowongan:** `aktif` `tidak_aktif`

**Nilai jenis_kerja:** `Full Time` `Part Time`

---

### 🤝 Rekruitmen

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| POST | `/api/rekruitmen` | ❌ | Daftar lamaran (publik) |
| POST | `/api/rekruitmen/cek` | ❌ | Cek status lamaran via token (publik) |
| GET | `/api/rekruitmen` | ✅ | Daftar pelamar (admin) |
| GET | `/api/rekruitmen/{id}` | ✅ | Detail pelamar |
| PUT | `/api/rekruitmen/{id}` | ✅ | Update data pelamar |
| DELETE | `/api/rekruitmen/{id}` | ✅ | Hapus lamaran |
| PATCH | `/api/rekruitmen/{id}/status` | ✅ | Update status terima |

**Nilai status_terima:** `pending` `diterima` `ditolak`

**File yang wajib diupload saat mendaftar:**
- `foto_ktp`, `foto_kk`, `foto_skck`, `pas_foto` — format: jpg/png, maks 2MB
- `surat_sehat`, `surat_anti_narkoba` — format: pdf/jpg/png, maks 2MB
- `surat_lamaran`, `cv` — format: pdf, maks 2MB

> ⚠️ Rate limit: **1 pendaftaran per menit** per IP.

---

### 📬 Kontak

| Method | Endpoint | Auth | Deskripsi |
|---|---|---|---|
| POST | `/api/kontak` | ❌ | Kirim pesan kontak (publik) |
| GET | `/api/kontak` | ✅ | Daftar pesan kontak |
| GET | `/api/kontak/{id}` | ✅ | Detail pesan (otomatis tandai dibaca) |
| PUT | `/api/kontak/{id}` | ✅ | Update data kontak |
| DELETE | `/api/kontak/{id}` | ✅ | Hapus pesan |
| POST | `/api/kontak/{id}/status` | ✅ | Update status baca |

**Nilai status_dibaca:** `pending` `dibaca`

> ⚠️ Rate limit: **1 pesan per menit** per IP.

---

## Fitur Export

### Export Excel
File Excel yang dihasilkan memiliki fitur:
- Header laporan perusahaan (nama, judul, periode, tanggal cetak)
- Format angka currency (`#,##0`)
- Zebra striping pada baris data
- Baris total dengan rumus `=SUM()` aktif
- **Rumus perhitungan aktif** — kolom kalkulasi menggunakan formula Excel sehingga tetap bisa diedit

### Rumus Excel aktif pada file export:

**Penggajian:**
| Kolom | Rumus |
|---|---|
| BPJS Kesehatan | `=IF(K<7, 0, F*0.01)` |
| BPJS JHT | `=IF(K<7, 0, F*0.02)` |
| BPJS JP | `=IF(K<7, 0, F*0.01)` |
| Upah Kotor | `=(L*K)+M+J` |
| Upah Diterima | `=N-(G+H+I)` |

**Tagihan Perusahaan:**
| Kolom | Rumus |
|---|---|
| Upah Diterima Pekerja | `=(O*N)+P+M` |
| Total Tagihan | `=Q+F+G+H+I+J+K+L` |

---

## Struktur Proyek

```
app/
├── Exports/
│   ├── KaryawanExport.php
│   ├── PenggajianExport.php
│   └── TagihanPerusahaanExport.php
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── DashboardController.php
│   │   │   ├── ExportPenggajianController.php
│   │   │   ├── ExportTagihanPerusahaanController.php
│   │   │   ├── KaryawanController.php
│   │   │   ├── KontakController.php
│   │   │   ├── LowonganKerjaController.php
│   │   │   ├── PenggajianController.php
│   │   │   ├── RekruitmenController.php
│   │   │   └── TagihanPerusahaanController.php
│   │   └── Auth/
│   │       ├── ChangePasswordController.php
│   │       ├── DeleteAccountController.php
│   │       ├── LoginController.php
│   │       ├── LogoutController.php
│   │       └── RegisterController.php
│   └── Resources/
│       ├── DashboardResource.php
│       ├── KaryawanResource.php
│       ├── KontakResource.php
│       ├── LowonganKerjaResource.php
│       ├── LowongankerjapublicResource.php
│       ├── PenggajianResource.php
│       ├── RekruitmenResource.php
│       ├── StatusTerimaResource.php
│       ├── TagihanPerusahaanResource.php
│       ├── ThrdllResource.php
│       └── UserResource.php
├── Imports/
│   └── KaryawanImport.php
└── Models/
    ├── Karyawan.php
    ├── Kontak.php
    ├── LowonganKerja.php
    ├── Penggajian.php
    ├── Rekruitmen.php
    ├── TagihanPerusahaan.php
    └── User.php
```

---

## Model & Relasi

```
User (Admin)
 ├── hasMany → Karyawan (admin_id, updated_by)
 ├── hasMany → Penggajian (admin_id, updated_by)
 ├── hasMany → TagihanPerusahaan (admin_id, updated_by)
 ├── hasMany → LowonganKerja (admin_id)
 ├── hasMany → Rekruitmen (admin_id)
 └── hasMany → Kontak (admin_id)

Karyawan
 ├── hasMany → Penggajian
 └── hasMany → TagihanPerusahaan

LowonganKerja
 └── hasMany → Rekruitmen
```

> Semua model `User` menggunakan **SoftDeletes**. Relasi ke User menggunakan `withTrashed()` agar data historis tetap terbaca meski admin dihapus.

---

## Catatan Penting

- **Soft Delete** digunakan pada: `User`, `Karyawan`, `Penggajian`, `TagihanPerusahaan`, `LowonganKerja`, `Kontak`
- **Kalkulasi BPJS dan upah** dilakukan di controller, bukan di model — field hasil kalkulasi tidak perlu dikirim dari client
- **Rate limiting** aktif pada endpoint publik: kontak dan rekruitmen (1 request/menit per IP)
- **Maksimal 5 akun admin** yang bisa terdaftar di sistem
