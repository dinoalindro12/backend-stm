<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kartu Karyawan</title>
    <style type="text/css">
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .card {
            width: 340px;
            height: 215px;
            border: 2px solid #2c3e50;
            position: relative;
        }
        
        .blue-strip {
            position: absolute;
            left: 0;
            top: 0;
            width: 12px;
            height: 100%;
            background: #3498db;
        }
        
        .header {
            padding: 8px 15px 6px 25px;
            border-bottom: 1px solid #ddd;
        }
        
        .title {
            font-size: 14px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .company {
            font-size: 10px;
            color: #7f8c8d;
            font-weight: bold;
        }
        
        .employee-id {
            position: absolute;
            top: 8px;
            right: 15px;
            background: #ecf0f1;
            color: #3498db;
            font-size: 11px;
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 3px;
        }
        
        .main-table {
            width: 100%;
            margin-top: 10px;
            padding-left: 25px;
        }
        
        .photo-cell {
            width: 65px;
            height: 85px;
            border: 1px solid #ccc;
            background: #f8f9fa;
            text-align: center;
            vertical-align: top;
        }
        
        .info-cell {
            padding-left: 15px;
            vertical-align: top;
        }
        
        .info-row {
            margin-bottom: 5px;
            font-size: 10px;
        }
        
        .label {
            font-weight: bold;
            color: #333;
            width: 55px;
            display: inline-block;
        }
        
        .footer {
            position: absolute;
            bottom: 10px;
            left: 25px;
            right: 15px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
        }
        
        .status {
            background: #27ae60;
            color: white;
            font-size: 9px;
            padding: 3px 8px;
            font-weight: bold;
            border-radius: 3px;
        }
        
        .year {
            float: right;
            font-size: 8px;
            color: #999;
            margin-top: 3px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="blue-strip"></div>
        <div class="employee-id">{{ $karyawan->nomor_induk }}</div>
        
        <div class="header">
            <div class="title">KARTU KARYAWAN</div>
            <div class="company">PT. SURYA TAMADO MANDIRI</div>
        </div>
        
        <table class="main-table">
            <tr>
                <td class="photo-cell" width="65" height="85">
                    @if($karyawan->image && file_exists(storage_path('app/public/' . $karyawan->image)))
                        <img src="{{ storage_path('app/public/' . $karyawan->image) }}" 
                             style="width: 100%; height: 100%; object-fit: cover;">
                    @else
                        <div style="padding-top: 30px; color: #999; font-size: 8px; line-height: 1.2;">
                            TIDAK ADA<br>FOTO
                        </div>
                    @endif
                </td>
                <td class="info-cell">
                    <div class="info-row">
                        <span class="label">NIK:</span> {{ $karyawan->nik }}
                    </div>
                    <div class="info-row">
                        <span class="label">NAMA:</span> {{ strtoupper($karyawan->nama_lengkap) }}
                    </div>
                    <div class="info-row">
                        <span class="label">POSISI:</span> {{ strtoupper(str_replace('_', ' ', $karyawan->posisi)) }}
                    </div>
                    <div class="info-row">
                        <span class="label">JOIN:</span> {{ \Carbon\Carbon::parse($karyawan->tanggal_masuk)->format('d/m/Y') }}
                    </div>
                </td>
            </tr>
        </table>
        
        <div class="footer">
            <span class="status">{{ $karyawan->status_aktif ? 'AKTIF' : 'TIDAK AKTIF' }}</span>
            <span class="year">KARTU ID RESMI {{ date('Y') }}</span>
        </div>
    </div>
</body>
</html>