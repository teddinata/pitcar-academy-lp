<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\BusinessMcpTool;
use App\Services\Ai\BusinessTools;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Pitcar Academy')]
#[Version('1.0.0')]
#[Instructions('Data kelas online Pitcar Academy, hanya baca: kelas, penjualan dan iklan, asal pengunjung, pesanan, siswa dan progresnya, diskusi kelas, masukan dan ulasan siswa. Uang dalam rupiah, waktu dalam WIB. Penjualan = pesanan DOKU lunas + pesanan Scalev selesai.')]
class PitcarAcademyServer extends Server
{
    protected function boot(): void
    {
        $this->tools = array_map(
            fn (array $tool) => new BusinessMcpTool($tool['name'], $tool['description'], $tool['inputSchema']),
            app(BusinessTools::class)->definitions(),
        );
    }
}
