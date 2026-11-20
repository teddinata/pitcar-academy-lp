<!doctype html>
<html lang="id">
<body style="margin:0;padding:24px;background:#FAFAF9;color:#0A0A0A;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6">
  <div style="max-width:520px;margin:0 auto">
    <p style="margin:0 0 20px;font-weight:700;font-size:18px">Pitcar Academy</p>
    <p style="margin:0 0 12px">Halo {{ $student->name }},</p>
    <p style="margin:0 0 12px">Kamu sudah membeli <strong>{{ implode(' dan ', $titles) }}</strong>. Mulai sekarang kelasnya dibuka di situs Pitcar Academy sendiri, tidak lagi lewat Scalev. Aksesmu sudah aktif dan tidak perlu bayar lagi.</p>
    <p style="margin:0 0 12px">Di sana progres tontonanmu tersimpan, kamu bisa bertanya di diskusi kelas, dan bertanya ke AI Pitcar Academy tentang materi.</p>
    <p style="margin:24px 0"><a href="{{ $url }}" style="display:inline-block;padding:14px 24px;background:#CC0000;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:2px">Masuk ke kelas</a></p>
    <p style="margin:0 0 12px;font-size:14px;color:#57534E">Tautan ini berlaku {{ intdiv($minutes, 60) }} jam dan hanya bisa dipakai sekali. Setelah itu, buka {{ config('lms.frontend_url') }}/belajar/masuk/ dan minta tautan baru dengan email ini.</p>
    <p style="margin:0;font-size:14px;color:#57534E">Kalau tombol tidak bisa ditekan, salin alamat ini ke browser:<br>{{ $url }}</p>
  </div>
</body>
</html>
