<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class NgrokStart extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ngrok:start
        {--port=8000 : Local port to expose}
        {--region=us : ngrok region}
        {--authtoken= : ngrok authtoken (optional)}
        {--detach : Do not stream logs; return after tunnel is created}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start or attach to ngrok and print public URL for webhooks (requires ngrok installed)';

    public function handle(): int
    {
        $port = (int) $this->option('port');
        $region = $this->option('region') ?: 'us';
        $authtoken = $this->option('authtoken');
        $detach = (bool) $this->option('detach');

        // Check ngrok binary
        $check = new Process(['ngrok', 'version']);
        try {
            $check->run();
        } catch (\Throwable $e) {
            $this->error('Ngrok binary not found in PATH. Install it from https://ngrok.com/download and ensure `ngrok` is available in your PATH.');
            return self::FAILURE;
        }

        if (! $check->isSuccessful()) {
            $this->error('Ngrok binary not found in PATH. Install it from https://ngrok.com/download and ensure `ngrok` is available in your PATH.');
            return self::FAILURE;
        }

        if ($authtoken) {
            $this->info('Setting ngrok authtoken...');
            $tokenCmd = new Process(['ngrok', 'authtoken', $authtoken]);
            $tokenCmd->run();
            if (! $tokenCmd->isSuccessful()) {
                $this->warn('Failed to set authtoken: ' . $tokenCmd->getErrorOutput());
            } else {
                $this->info('ngrok authtoken set.');
            }
        }

        $this->info("Starting ngrok on port {$port} (region={$region})...");

        $process = new Process(['ngrok', 'http', (string) $port, '--log=stdout', '--region=' . $region]);
        $process->setTimeout(null);
        $process->start();

        // Save PID for convenience
        $pid = $process->getPid();
        if ($pid) {
            try {
                File::put(base_path('.ngrok.pid'), (string) $pid);
            } catch (\Throwable $e) {
                // ignore file write errors
            }
        }

        // Wait for ngrok web API to be available
        $this->info('Waiting for ngrok tunnel (timeout 20s)...');
        $deadline = time() + 20;
        $publicUrl = null;

        while (time() < $deadline) {
            try {
                $res = Http::timeout(2)->get('http://127.0.0.1:4040/api/tunnels');
                if ($res->ok()) {
                    $data = $res->json();
                    if (! empty($data['tunnels']) && is_array($data['tunnels'])) {
                        // prefer https
                        foreach ($data['tunnels'] as $tunnel) {
                            if (isset($tunnel['proto']) && $tunnel['proto'] === 'https') {
                                $publicUrl = $tunnel['public_url'];
                                break;
                            }
                        }

                        if (! $publicUrl && ! empty($data['tunnels'][0]['public_url'])) {
                            $publicUrl = $data['tunnels'][0]['public_url'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore until timeout
            }

            if ($publicUrl) {
                break;
            }

            usleep(300000);
        }

        if (! $publicUrl) {
            $this->warn('Unable to determine ngrok public URL. You can run `ngrok http ' . $port . '` manually and re-run this command to check status.');
            return self::FAILURE;
        }

        $this->info('ngrok tunnel: ' . $publicUrl);
        $this->line('Midtrans webhook URL: ' . rtrim($publicUrl, '/') . '/midtrans/webhook');

        $this->line('Tip: set APP_URL in your .env to the ngrok URL if you want app-generated links to use it.');

        if ($detach) {
            $this->info('Detached. ngrok process should continue in background (if your OS supports it). PID saved in .ngrok.pid');
            return self::SUCCESS;
        }

        $this->info('Streaming ngrok output (Ctrl+C to stop). To leave ngrok running, start it manually with `ngrok http ' . $port . ' &`');

        // Stream incremental output for user convenience
        try {
            while ($process->isRunning()) {
                $out = $process->getIncrementalOutput();
                $err = $process->getIncrementalErrorOutput();
                if ($out !== '') {
                    $this->output->write($out);
                }
                if ($err !== '') {
                    $this->output->write($err);
                }
                usleep(200000);
            }
        } catch (\Throwable $e) {
            // ignore streaming errors
        }

        return self::SUCCESS;
    }
}
