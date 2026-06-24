@component('mail::message')
# Pendaftaran berhasil

Halo {{ $rekruitmen->nama }},

Terima kasih telah melamar untuk posisi **{{ $rekruitmen->posisi_dilamar }}**.

Token pendaftaran Anda:

@component('mail::panel')
{{ $rekruitmen->token_pendaftaran }}
@endcomponent

Simpan token ini untuk mengecek status lamaran Anda kapan saja.

Terima kasih,<br>
Tim Rekrutmen
@endcomponent