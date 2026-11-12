<?php

use App\Models\Asset;
use App\Models\Report;
use App\Models\TechnicalChief;
use App\Models\User;
use App\Services\StaffAssignments;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('activos', 'responsable_id')) {
            Schema::table('activos', fn (Blueprint $table) => $table->foreignId('responsable_id')->nullable()->constrained('usuarios')->nullOnDelete());
        }
        DB::transaction(function () {
            $users = User::with('nombres')->get()->groupBy(fn (User $user) => mb_strtolower(trim($user->nombre)));
            Asset::whereNull('responsable_id')->eachById(function (Asset $asset) use ($users) {
                $matches = $users->get(mb_strtolower(trim($asset->responsable)));
                if ($matches?->count() === 1) {
                    DB::table('activos')->where('id', $asset->id)->update(['responsable_id' => $matches->first()->id]);
                }
            });
            $assignments = new StaffAssignments();
            User::where('activo', false)->lockForUpdate()->eachById(fn (User $user) => $assignments->release($user));
            if ($chief = TechnicalChief::firstActiveId()) {
                Report::open()->whereNull('jefe_id')->update(['jefe_id' => $chief]);
            }
        }, 3);
    }

    public function down(): void
    {
        Schema::table('activos', fn (Blueprint $table) => $table->dropConstrainedForeignId('responsable_id'));
    }
};
