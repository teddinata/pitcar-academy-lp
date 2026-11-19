<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rebuilds the landing site so its printed prices follow the CMS.
 *
 * The site is static: prices are read from the LMS API at build time. The
 * checkout always charges the database price; this only refreshes what the
 * sales pages show. It runs the existing "Deploy to VPS" workflow through
 * GitHub's workflow_dispatch API.
 */
class SiteRebuild
{
    public function configured(): bool
    {
        return filled(config('services.site_deploy.repo')) && filled(config('services.site_deploy.token'));
    }

    /** @return array{ok: bool, message: string} */
    public function dispatch(?string $by = null): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'message' => 'Belum diatur: isi SITE_DEPLOY_REPO dan SITE_DEPLOY_TOKEN di .env server.'];
        }

        $repo = config('services.site_deploy.repo');
        $workflow = config('services.site_deploy.workflow');

        try {
            $response = Http::withToken((string) config('services.site_deploy.token'))
                ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
                ->timeout(15)
                ->post("https://api.github.com/repos/{$repo}/actions/workflows/{$workflow}/dispatches", [
                    'ref' => config('services.site_deploy.ref'),
                ]);
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => 'GitHub tidak bisa dihubungi. Coba lagi sebentar lagi.'];
        }

        if ($response->status() === 204) {
            Log::info('site.rebuild_dispatched', ['repo' => $repo, 'by' => $by]);

            return ['ok' => true, 'message' => 'Situs sedang diterbitkan ulang. Harga baru tampil di landing page dalam beberapa menit.'];
        }

        Log::warning('site.rebuild_failed', ['repo' => $repo, 'status' => $response->status(), 'error' => $response->json('message')]);

        return ['ok' => false, 'message' => 'GitHub menolak permintaan (HTTP '.$response->status().'). Periksa token dan nama repo.'];
    }
}
