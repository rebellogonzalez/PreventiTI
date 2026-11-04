<?php

namespace App\Console\Commands;

use App\Models\Audit;
use App\Models\User;
use App\Support\AccountPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class IssueAccountCredentials extends Command
{
    protected $signature = 'accounts:issue-credentials {output : Private Markdown file outside public/}';
    protected $description = 'Assign individual passwords and invalidate previous sessions.';

    public function handle(): int
    {
        $path = $this->argument('output');
        $directory = realpath(dirname($path));
        if (! $directory || str_starts_with($directory.'/', public_path().'/') || file_exists($path)) {
            $this->error('Use a new file in an existing private directory outside public/.');
            return self::FAILURE;
        }
        $users = User::with('nombres')->orderBy('id')->get();
        $credentials = $users->map(fn (User $user) => ['user' => $user, 'password' => AccountPassword::generate($user->email)]);
        $markdown = "# Credenciales de PreventiTI\n\nEmitidas el 21 de octubre de 2026. Uso privado.\n\n| Nombre | Rol | Correo de acceso | Contraseña |\n| --- | --- | --- | --- |\n";
        foreach ($credentials as $entry) {
            $user = $entry['user'];
            $markdown .= "| {$user->nombre} | {$user->role_title} | {$user->email} | `{$entry['password']}` |\n";
        }
        $oldMask = umask(0077);
        $file = fopen($path, 'x');
        umask($oldMask);
        if (! $file) return self::FAILURE;
        try {
            if (fwrite($file, $markdown) !== strlen($markdown)) throw new \RuntimeException('Incomplete credentials file.');
            fflush($file);
            DB::transaction(function () use ($credentials) {
                foreach ($credentials as $entry) {
                    $user = $entry['user'];
                    $user->forceFill(['password' => Hash::make($entry['password']), 'remember_token' => null])->save();
                }
                DB::table('sessions')->delete();
                Audit::create(['fecha_auditoria' => now(), 'descripcion' => 'Emisión de credenciales individuales para '.$credentials->count().' cuentas; sesiones anteriores revocadas.']);
            });
        } catch (\Throwable $exception) {
            fclose($file);
            unlink($path);
            throw $exception;
        }
        fclose($file);
        $this->info($credentials->count().' cuentas actualizadas. Listado privado: '.$path);
        return self::SUCCESS;
    }
}
