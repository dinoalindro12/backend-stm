<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kartu Karyawan Bulk</title>
    <style>
        @page {
            margin: 10mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
        }
        
        .page-break {
            page-break-after: always;
        }
        
        /* Copy semua style dari kartu-karyawan.blade.php */
        /* ... (sama seperti di atas) ... */
    </style>
</head>
<body>
    @foreach($karyawans as $index => $karyawan)
        <div class="card-container">
            <!-- Same content as single card -->
        </div>
        
        @if(!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>