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
        $oldMask = umask(0077);
        $file = fopen($path, 'x');
        umask($oldMask);
        if (! $file) return self::FAILURE;
        try {
            $count = DB::transaction(function () use ($file) {
                $users = User::with('nombres')->where('activo', true)->orderBy('id')->lockForUpdate()->get();
                $markdown = "# Credenciales de PreventiTI\n\nEmitidas el ".now()->format('d/m/Y').". Uso privado.\n\n| Nombre | Rol | Correo de acceso | Contraseña |\n| --- | --- | --- | --- |\n";
                foreach ($users as $user) {
                    $password = AccountPassword::generate($user->email);
                    $markdown .= "| {$user->nombre} | {$user->role_title} | {$user->email} | `{$password}` |\n";
                    $user->forceFill(['password' => Hash::make($password), 'remember_token' => null])->save();
                }
                if (fwrite($file, $markdown) !== strlen($markdown) || ! fflush($file)) throw new \RuntimeException('Incomplete credentials file.');
                DB::table('sessions')->delete();
                Audit::create(['fecha_auditoria' => now(), 'descripcion' => 'Emisión de credenciales individuales para '.$users->count().' cuentas; sesiones anteriores revocadas.']);
                return $users->count();
            });
        } catch (\Throwable $exception) {
            fclose($file);
            unlink($path);
            throw $exception;
        }
        fclose($file);
        $this->info($count.' cuentas actualizadas. Listado privado: '.$path);
        return self::SUCCESS;
    }
}
