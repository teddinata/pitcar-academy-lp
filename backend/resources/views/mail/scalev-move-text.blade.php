Halo {{ $student->name }},

Kamu sudah membeli {{ implode(' dan ', $titles) }}. Mulai sekarang kelasnya dibuka di situs Pitcar Academy sendiri, tidak lagi lewat Scalev. Aksesmu sudah aktif dan tidak perlu bayar lagi.

Di sana progres tontonanmu tersimpan, kamu bisa bertanya di diskusi kelas, dan bertanya ke AI Pitcar Academy tentang materi.

Masuk ke kelas: {{ $url }}

Tautan ini berlaku {{ intdiv($minutes, 60) }} jam dan hanya bisa dipakai sekali. Setelah itu, buka {{ config('lms.frontend_url') }}/belajar/masuk/ dan minta tautan baru dengan email ini.

Pitcar Academy
