<!doctype html>
<html lang="id">
<body style="margin:0;padding:24px;background:#FAFAF9;color:#0A0A0A;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6">
  <div style="max-width:520px;margin:0 auto">
    <p style="margin:0 0 20px;font-weight:700;font-size:18px">Pitcar Academy</p>
    <p style="margin:0 0 12px">Halo {{ $first }},</p>
    <p style="margin:0 0 12px">Kamu sempat memesan <strong>{{ $title }}</strong>, tapi pembayarannya belum selesai.</p>
    <p style="margin:0 0 12px">Sekarang pemesanan kelas online pindah ke situs Pitcar Academy sendiri. Setelah bayar, materinya langsung terbuka dan bisa ditonton dari HP atau laptop.</p>
    <p style="margin:24px 0"><a href="{{ $url }}" style="display:inline-block;padding:14px 24px;background:#CC0000;color:#FFFFFF;text-decoration:none;font-weight:700;border-radius:2px">Lanjutkan pesanan</a></p>
    <p style="margin:0 0 12px">@if ($price)Harganya saat ini {{ $price }}. @endif Tagihan yang lama tidak perlu dibayar.</p>
    <p style="margin:0 0 12px;font-size:14px;color:#57534E">{{ $canReply ? 'Ada pertanyaan soal kelasnya? Balas email ini saja.' : 'Kalau sudah tidak berminat, abaikan email ini.' }}</p>
    <p style="margin:0;font-size:14px;color:#57534E">Kalau tombol tidak bisa ditekan, salin alamat ini ke browser:<br>{{ $url }}</p>
  </div>
</body>
</html>
