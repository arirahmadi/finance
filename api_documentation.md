# Dokumentasi API Keuangan (`finance-sys`)

Dokumentasi ini menjelaskan endpoints API yang tersedia pada backend Laravel untuk dapat dikonsumsi oleh aplikasi eksternal seperti **Flutter Mobile App**.

## Informasi Umum
- **Base URL:** `http://127.0.0.1:8000/api`
- **Default Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json` (Kecuali untuk upload file yang menggunakan `multipart/form-data`)

---

## 1. Alur Autentikasi (Authentication)

Semua endpoint yang dilindungi memerlukan header:
`Authorization: Bearer <access_token>`

### 1.1 Registrasi User Baru
Mendaftarkan akun pengguna baru ke sistem.

- **Method & Route:** `POST /register`
- **Request Body (JSON):**
```json
{
  "name": "Jane Doe",
  "email": "jane@finance.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```
- **Response Sukses (201 Created):**
```json
{
  "message": "User registered successfully.",
  "access_token": "1|qR6sYk...",
  "token_type": "Bearer",
  "user": {
    "id": 2,
    "name": "Jane Doe",
    "email": "jane@finance.com",
    "created_at": "2026-07-04T15:00:00.000000Z",
    "updated_at": "2026-07-04T15:00:00.000000Z"
  }
}
```

### 1.2 Login User
Mendapatkan token akses untuk transaksi terautentikasi.

- **Method & Route:** `POST /login`
- **Request Body (JSON):**
```json
{
  "email": "admin@finance.com",
  "password": "password123"
}
```
- **Response Sukses (200 OK):**
```json
{
  "message": "Login successful.",
  "access_token": "1|zX7mP...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Admin Finance",
    "email": "admin@finance.com",
    "created_at": "2026-07-04T15:00:00.000000Z",
    "updated_at": "2026-07-04T15:00:00.000000Z"
  }
}
```
- **Response Gagal (422 Unprocessable Entity):**
```json
{
  "message": "Kredensial yang diberikan salah.",
  "errors": {
    "email": [
      "Kredensial yang diberikan salah."
    ]
  }
}
```

### 1.3 Logout User
Mencabut / menghapus token aktif saat ini.

- **Method & Route:** `POST /logout`
- **Headers:** `Authorization: Bearer <access_token>`
- **Response Sukses (200 OK):**
```json
{
  "message": "Logged out successfully."
}
```

### 1.4 Get Profile (User Info)
Melihat data diri user yang sedang aktif.

- **Method & Route:** `GET /user`
- **Headers:** `Authorization: Bearer <access_token>`
- **Response Sukses (200 OK):**
```json
{
  "user": {
    "id": 1,
    "name": "Admin Finance",
    "email": "admin@finance.com",
    "created_at": "2026-07-04T15:00:00.000000Z",
    "updated_at": "2026-07-04T15:00:00.000000Z"
  }
}
```

---

## 2. Bagan Akun (Chart of Accounts / COA)

### 2.1 List Bagan Akun
Mendapatkan daftar semua kategori akun keuangan untuk pilihan dropdown di aplikasi Flutter.

- **Method & Route:** `GET /accounts`
- **Headers:** `Authorization: Bearer <access_token>`
- **Response Sukses (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "code": "1101",
      "name": "Kas Utama",
      "type": "asset"
    },
    {
      "id": 2,
      "code": "1102",
      "name": "Bank Mandiri/BCA",
      "type": "asset"
    },
    {
      "id": 9,
      "code": "5101",
      "name": "Beban Gaji & Tunjangan",
      "type": "expense"
    }
  ]
}
```

---

## 3. Transaksi & Laporan Keuangan

### 3.1 List Transaksi & Ringkasan Laporan
Mendapatkan semua daftar transaksi beserta ringkasan total uang masuk, uang keluar, dan saldo bersih.

- **Method & Route:** `GET /transactions`
- **Headers:** `Authorization: Bearer <access_token>`
- **Query Parameters (Opsional):**
  - `start_date` (Format: `YYYY-MM-DD`): Awal rentang tanggal laporan.
  - `end_date` (Format: `YYYY-MM-DD`): Akhir rentang tanggal laporan.
- **Response Sukses (200 OK):**
```json
{
  "summary": {
    "total_in": 500000,
    "total_out": 120000,
    "net_flow": 380000,
    "period": {
      "start": "2026-07-01",
      "end": "2026-07-04"
    }
  },
  "data": [
    {
      "id": 12,
      "transaction_number": "TX-20260704-0002",
      "transaction_date": "2026-07-04",
      "description": "Beli Kopi Rapat Tim",
      "type": "out",
      "amount": 120000,
      "category": "Beban Sewa & Operasional Kantor",
      "payment_source": "Kas Utama",
      "attachments": [
        {
          "id": 3,
          "transaction_id": 12,
          "file_path": "receipts/abc-123.png",
          "original_name": "kopi.png",
          "file_size": 24500,
          "created_at": "2026-07-04T15:10:00.000000Z",
          "updated_at": "2026-07-04T15:10:00.000000Z",
          "url": "http://127.0.0.1:8000/storage/receipts/abc-123.png"
        }
      ],
      "created_by": "Admin Finance"
    }
  ]
}
```

### 3.2 Tambah Transaksi Baru (In/Out)
Memasukkan transaksi pemasukan (in) atau pengeluaran (out) beserta unggah gambar bon bukti pembayaran.
> **Penting:** Endpoint ini harus dikirim menggunakan format **`multipart/form-data`** jika menyertakan file `receipt`.

- **Method & Route:** `POST /transactions`
- **Headers:** 
  - `Authorization: Bearer <access_token>`
  - `Accept: application/json`
- **Request Body (multipart/form-data):**

| Key | Type | Required | Description |
|---|---|---|---|
| `type` | string | **Yes** | Nilai harus `'in'` (Uang Masuk) atau `'out'` (Uang Keluar). |
| `amount` | numeric | **Yes** | Jumlah nominal uang (Contoh: `150000`). Min: `0.01`. |
| `account_id` | integer | **Yes** | ID dari Bagan Akun (COA) kategori beban/pendapatan. |
| `payment_account_id` | integer | **Yes** | ID dari Bagan Akun (COA) Kas Utama / Bank BCA. |
| `transaction_date` | date | No | Format: `YYYY-MM-DD`. Default: Tanggal hari ini. |
| `description` | string | No | Catatan tambahan transaksi. Max: 500 karakter. |
| `receipt` | file | No | File bukti bon (Format: `.jpeg`, `.png`, `.jpg`, `.pdf`, Max: 5MB). |

- **Response Sukses (201 Created):**
```json
{
  "message": "Transaction saved successfully.",
  "data": {
    "id": 13,
    "transaction_number": "TX-20260704-0003",
    "transaction_date": "2026-07-04T00:00:00.000000Z",
    "description": "Pembayaran server hosting bulanan",
    "created_by": 1,
    "created_at": "2026-07-04T15:12:00.000000Z",
    "updated_at": "2026-07-04T15:12:00.000000Z",
    "journal_entries": [
      {
        "id": 25,
        "transaction_id": 13,
        "account_id": 11,
        "type": "debit",
        "amount": "150000.00",
        "account": {
          "id": 11,
          "code": "5103",
          "name": "Beban Server & Langganan Software",
          "type": "expense"
        }
      },
      {
        "id": 26,
        "transaction_id": 13,
        "account_id": 2,
        "type": "credit",
        "amount": "150000.00",
        "account": {
          "id": 2,
          "code": "1102",
          "name": "Bank Mandiri/BCA",
          "type": "asset"
        }
      }
    ],
    "attachments": [
      {
        "id": 4,
        "transaction_id": 13,
        "file_path": "receipts/xyz-987.png",
        "original_name": "invoice_cloud.png",
        "file_size": 42000,
        "url": "http://127.0.0.1:8000/storage/receipts/xyz-987.png"
      }
    ]
  }
}
```

### 3.3 Detail Transaksi Tunggal
Mendapatkan detail dari satu ID transaksi tertentu.

- **Method & Route:** `GET /transactions/{id}`
- **Headers:** `Authorization: Bearer <access_token>`
- **Response Sukses (200 OK):**
Mendapatkan struktur objek detail transaksi yang sama seperti properti `data` di atas.

---

## 4. Contoh Request Uji Coba (Curl Examples)

Berikut adalah contoh perintah `curl` untuk menguji endpoint API secara manual dari terminal.

### 4.1 Mengambil Token Login
```bash
curl -X POST http://127.0.0.1:8000/api/login \
     -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -d '{"email": "admin@finance.com", "password": "password123"}'
```

### 4.2 Mengambil Bagan Akun (COA)
```bash
curl -X GET http://127.0.0.1:8000/api/accounts \
     -H "Accept: application/json" \
     -H "Authorization: Bearer <ganti_dengan_token>"
```

### 4.3 Mengambil Daftar & Ringkasan Laporan Transaksi
```bash
curl -X GET "http://127.0.0.1:8000/api/transactions?start_date=2026-07-01&end_date=2026-07-04" \
     -H "Accept: application/json" \
     -H "Authorization: Bearer <ganti_dengan_token>"
```

### 4.4 Membuat Transaksi Baru dengan Upload Bon
```bash
curl -X POST http://127.0.0.1:8000/api/transactions \
     -H "Accept: application/json" \
     -H "Authorization: Bearer <ganti_dengan_token>" \
     -F "type=out" \
     -F "amount=150000" \
     -F "account_id=11" \
     -F "payment_account_id=2" \
     -F "description=Pembelian Vercel Hosting" \
     -F "receipt=@/path/to/your/receipt.png"
```

