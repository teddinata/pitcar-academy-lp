Halo {{ $student->name }},

@if ($course)
Pembayaranmu sudah kami terima dan {{ $title ?? $course->title }} sudah terbuka.
@else
Ini tautan untuk masuk ke kelasmu.
@endif

Masuk ke kelas: {{ $url }}

Tautan ini berlaku {{ $minutes >= 120 ? intdiv($minutes, 60).' jam' : $minutes.' menit' }} dan hanya bisa dipakai sekali. Setelah itu, minta tautan baru di halaman Masuk dengan email ini.

Pitcar Academy
