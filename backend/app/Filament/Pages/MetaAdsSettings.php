<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Services\Analytics\MetaAds;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use RuntimeException;
use UnitEnum;

/**
 * Connects the analytics page to Meta Ads: the ad account and a System User
 * token with ads_read. The token is stored encrypted and never shown again.
 */
class MetaAdsSettings extends Page
{
    protected static ?string $title = 'Iklan Meta';

    protected static ?string $slug = 'pengaturan/iklan-meta';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    public ?array $data = [];

    // A token gives read access to the ad account's money figures: admins only.
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(['account_id' => AppSetting::get(MetaAds::ACCOUNT), 'token' => null]);
    }

    public function form(Schema $schema): Schema
    {
        $saved = app(MetaAds::class)->token();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Akun iklan')
                    ->description($this->status())
                    ->schema([
                        TextInput::make('account_id')
                            ->label('Ad Account ID')
                            ->placeholder('1234567890')
                            ->helperText('Angka di Ads Manager, di menu akun kiri atas atau di alamat setelah act=. Boleh ditulis dengan atau tanpa "act_".')
                            ->required()
                            ->regex('/^(act_)?\d{5,25}$/')
                            ->validationMessages(['regex' => 'Isi angkanya saja, misalnya 1234567890 atau act_1234567890.']),
                        TextInput::make('token')
                            ->label('Access token')
                            ->password()
                            ->autocomplete('off')
                            ->placeholder($saved ? 'Tersimpan, berakhiran …'.substr($saved, -4).'. Kosongkan kalau tidak diganti.' : 'Tempel token dari Business Settings')
                            ->required(! $saved)
                            ->maxLength(1000),
                    ]),
                Section::make('Cara membuat token')
                    ->collapsed()
                    ->schema([
                        Text::make('1. Buka business.facebook.com › Pengaturan bisnis › Pengguna › Pengguna sistem. Buat pengguna sistem baru dengan peran Admin, atau pakai yang sudah ada.'),
                        Text::make('2. Klik Tetapkan aset › Akun iklan › pilih akun iklan Pitcar Academy › centang Lihat performa.'),
                        Text::make('3. Klik Buat token baru › pilih aplikasi (aplikasi yang dipakai untuk pixel juga bisa) › masa berlaku: Tidak pernah › centang izin ads_read › Buat token.'),
                        Text::make('4. Salin tokennya ke kolom Access token di atas, lalu Simpan. Token hanya ditampilkan sekali oleh Meta.'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Simpan dan tes koneksi')->submit('save'),
                        Action::make('backfill')
                            ->label('Ambil data 90 hari terakhir')
                            ->color('gray')
                            ->visible(fn () => app(MetaAds::class)->configured())
                            ->action(fn () => $this->backfill()),
                    ]),
                ]),
        ]);
    }

    public function save(MetaAds $meta): void
    {
        $data = $this->form->getState();
        AppSetting::put(MetaAds::ACCOUNT, preg_replace('/\D/', '', $data['account_id']));
        if (filled($data['token'] ?? null)) {
            AppSetting::putSecret(MetaAds::TOKEN, trim($data['token']));
        }

        try {
            $account = $meta->check();
            AppSetting::put(MetaAds::LAST_ERROR, null);
            Notification::make()->success()
                ->title('Terhubung ke '.$account['name'])
                ->body('Mata uang akun: '.$account['currency'].'. Data 7 hari terakhir sedang diambil; sisanya lewat tombol "Ambil data 90 hari terakhir".')
                ->send();
            $this->pullDays(7);
        } catch (RuntimeException $e) {
            AppSetting::put(MetaAds::LAST_ERROR, $e->getMessage());
            Notification::make()->danger()->title('Tersimpan, tapi belum bisa terhubung')->body($e->getMessage())->persistent()->send();
        }

        $this->form->fill(['account_id' => AppSetting::get(MetaAds::ACCOUNT), 'token' => null]);
    }

    public function backfill(): void
    {
        if ($this->pullDays(90)) {
            Notification::make()->success()->title('Data 90 hari terakhir sudah diambil')->send();
        }
    }

    private function pullDays(int $days): bool
    {
        $today = CarbonImmutable::now(config('app.display_timezone'))->startOfDay();

        try {
            app(MetaAds::class)->sync($today->subDays($days - 1), $today);

            return true;
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title('Data Meta gagal diambil')->body($e->getMessage())->persistent()->send();

            return false;
        }
    }

    private function status(): string
    {
        $meta = app(MetaAds::class);

        if (! $meta->configured()) {
            return 'Belum terhubung. Isi kedua kolom di bawah, lalu simpan.';
        }

        $error = AppSetting::get(MetaAds::LAST_ERROR);
        $synced = AppSetting::get(MetaAds::SYNCED_AT);

        return match (true) {
            filled($error) => 'Bermasalah: '.$error,
            filled($synced) => 'Terhubung. Data diambil otomatis tiap jam, terakhir '.CarbonImmutable::parse($synced)->setTimezone(config('app.display_timezone'))->format('d M Y H:i').' WIB.',
            default => 'Tersimpan. Pengambilan data pertama berjalan dalam satu jam.',
        };
    }
}
